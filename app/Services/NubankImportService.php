<?php

namespace App\Services;

use App\Models\BalanceSnapshot;
use App\Models\CreditCard;
use App\Models\FixedExpense;
use App\Models\InvestmentBox;
use App\Models\InvestmentTransaction;
use App\Models\Salary;
use App\Models\StatementPayment;
use App\Models\VariableExpense;
use App\Models\VariableIncome;
use Carbon\Carbon;
use Closure;

/**
 * Importa a movimentação da conta Nubank e as caixinhas direto da API do Pluggy.
 *
 * Mesma classificação do import:variable-expenses, mas lendo a API em vez do CSV.
 * Como as transações do CSV têm o id do Nubank e as da API têm o id do Pluggy, a
 * deduplicação é feita reconciliando por (data, valor): insere apenas a diferença
 * entre o que a API traz e o que já existe no banco.
 *
 * Vive aqui, e não dentro do comando, porque roda por dois caminhos — o comando
 * `import:pluggy-nubank` e o botão do dashboard — e os dois precisam se comportar
 * exatamente igual. O log sai como NubankImportMessage: o comando imprime cada uma
 * assim que chega, a web lê a lista pronta no fim.
 */
class NubankImportService
{
    private NubankImportOptions $options;

    private NubankImportResult $result;

    private ?Closure $onMessage = null;

    public function __construct(
        private readonly PluggyService $pluggy,
        private readonly StatementClassifier $classifier,
        private readonly SalaryCredit $salaryCredit,
        private readonly InvestmentService $investments,
        private readonly ThirdPartyReimbursement $reimbursement,
    ) {}

    /**
     * @param  callable(NubankImportMessage): void|null  $onMessage  recebe cada linha do log
     *                                                               no momento em que acontece
     *
     * @throws NubankImportException
     */
    public function import(NubankImportOptions $options, ?callable $onMessage = null): NubankImportResult
    {
        $itemId = config('pluggy.items.nubank');

        if (! $itemId) {
            throw new NubankImportException('PLUGGY_ITEM_ID_NUBANK não configurado no .env.');
        }

        $account = collect($this->pluggy->listAccounts($itemId))->firstWhere('type', 'BANK');

        if (! $account) {
            throw new NubankImportException('Conta corrente do Nubank não encontrada no Pluggy.');
        }

        $period = BillingPeriod::for(now());
        $from = $options->from ?? $period['start']->copy()->startOfDay();
        $to = $options->to ?? now()->endOfDay();

        $this->options = $options;
        $this->onMessage = $onMessage === null ? null : Closure::fromCallable($onMessage);
        $this->result = new NubankImportResult($from, $to, $options->dryRun);

        if ($options->dryRun) {
            $this->warn('Modo dry-run: nada será gravado.');
        }

        $this->info("Período: {$from->toDateString()} a {$to->toDateString()}");
        $this->blank();

        $transactions = $this->pluggy->listTransactions($account['id'], $from, $to);
        $this->result->apiTransactionCount = count($transactions);
        $this->line(count($transactions).' lançamentos na API.');

        $candidates = $this->buildCandidates($transactions);
        $this->reconcile($candidates);

        $this->blank();
        $boxes = $this->syncInvestments($itemId);
        $this->recordBalance($account, $boxes, $from, $period);

        return $this->result;
    }

    /**
     * Guarda o saldo real desta leitura. É a âncora do acumulado da projeção: o
     * dashboard lê daqui em vez de consultar a API a cada carregamento, então o
     * saldo é o do momento em que o usuário mandou atualizar.
     *
     * `salary_credited` só é afirmado quando a janela lida cobre o dia da queda.
     * Fora disso o salário poderia ter caído sem aparecer aqui, e o null deixa a
     * projeção decidir pelo calendário, como já fazia sem resposta do extrato.
     */
    private function recordBalance(array $account, float $boxes, Carbon $from, array $period): void
    {
        if ($this->options->dryRun) {
            return;
        }

        $checking = round((float) ($account['balance'] ?? 0), 2);

        BalanceSnapshot::create([
            'checking' => $checking,
            'boxes' => round($boxes, 2),
            'total' => round($checking + $boxes, 2),
            'salary_credited' => $from->lte($period['start'])
                ? $this->result->salary !== null
                : null,
            'synced_at' => now(),
        ]);
    }

    /**
     * Traduz cada lançamento da API na linha que ele geraria no banco.
     * Retorna itens no formato ['model' => ..., 'date' => ..., 'amount' => ..., 'attributes' => [...]].
     */
    private function buildCandidates(array $transactions): array
    {
        $candidates = [];

        foreach ($transactions as $transaction) {
            $date = Carbon::parse($transaction['date'])->toDateString();
            $amount = (float) $transaction['amount'];
            $description = $transaction['description'] ?? '';
            $identifier = 'pluggy:'.$transaction['id'];

            // Caixinhas vêm do endpoint de investimentos, com a taxa real de cada posição.
            if ($this->classifier->isInvestmentMovement($description)) {
                $this->result->investmentMovements++;

                continue;
            }

            // O salário chega como transferência do próprio titular vinda de outro banco.
            // A projeção já o conta via salaryForMonth(), então não pode entrar como
            // receita variável. As outras transferências próprias entram normalmente:
            // aqui só o saldo do Nubank é rastreado, e elas movem esse saldo.
            if ($this->salaryCredit->matches($transaction)) {
                $this->result->confirmed++;
                $adjustment = $this->salaryAdjustment($amount, $date, $identifier);

                if ($adjustment !== null) {
                    $candidates[] = $adjustment;
                }

                continue;
            }

            // A projeção já soma a receita recorrente em todo ciclo.
            if ($this->salaryCredit->isRecurringIncome($transaction)) {
                $this->result->confirmed++;
                $this->line('  ✓ Receita recorrente confirmada: R$ '.$this->money($amount)." em {$date}");

                continue;
            }

            // Devolução de compra feita para outra pessoa: a compra está fora dos
            // cálculos, então este dinheiro também fica.
            if ($this->reimbursement->matches($transaction)) {
                $this->result->confirmed++;
                $this->line('  ✓ Devolução de compra de terceiro: R$ '.$this->money($amount)." em {$date}");

                continue;
            }

            $fixedMatch = $this->classifier->matchFixedExpense($description);

            if ($fixedMatch !== null) {
                $this->result->confirmed++;
                $adjustment = $this->fixedExpenseAdjustment($fixedMatch, $amount, $date, $identifier);

                if ($adjustment !== null) {
                    $candidates[] = $adjustment;
                }

                continue;
            }

            $candidates[] = $amount < 0
                ? [
                    'model' => VariableExpense::class,
                    'date' => $date,
                    'amount' => round(abs($amount), 2),
                    'attributes' => ['description' => $description, 'amount' => round(abs($amount), 2), 'paid_at' => $date],
                    'identifier' => $identifier,
                ]
                : [
                    'model' => VariableIncome::class,
                    'date' => $date,
                    'amount' => round($amount, 2),
                    'attributes' => ['description' => $description, 'amount' => round($amount, 2), 'received_at' => $date],
                    'identifier' => $identifier,
                ];
        }

        $this->line($this->result->investmentMovements.' movimentações RDB (tratadas na sincronia das caixinhas).');
        $this->line($this->result->confirmed.' gastos fixos / salário confirmados.');
        $this->blank();

        return $candidates;
    }

    /**
     * Marca a conta fixa/fatura como já debitada da conta neste ciclo. O acumulado
     * parte do saldo real, que já reflete esse débito, então não pode somá-lo de novo.
     */
    private function recordPayment(
        string $label,
        string $date,
        float $amount,
        string $identifier,
        ?FixedExpense $fixedExpense = null,
        ?string $creditCardName = null,
    ): void {
        if ($this->options->dryRun) {
            return;
        }

        StatementPayment::updateOrCreate(
            ['identifier' => $identifier],
            [
                'label' => $label,
                'fixed_expense_id' => $fixedExpense?->id,
                'credit_card_id' => $creditCardName
                    ? CreditCard::where('name', $creditCardName)->value('id')
                    : null,
                'amount' => round(abs($amount), 2),
                'paid_at' => $date,
            ]
        );
    }

    /**
     * Só a diferença entre o que caiu e o salário cadastrado vira lançamento: o
     * valor cheio a projeção já conta por conta própria, e importá-lo de novo
     * somaria o mesmo dinheiro duas vezes. Recebeu a mais, é receita variável;
     * a menos, despesa — mesmo tratamento dado às contas fixas.
     *
     * Receita recorrente pode cair no mesmo crédito. Ela só é descontada se a sobra
     * a cobre inteira: abaixo disso, foi paga à parte e a sobra é ajuste de verdade.
     */
    private function salaryAdjustment(float $amount, string $date, string $identifier): ?array
    {
        $expected = (float) (Salary::where('effective_from', '<=', $date)->orderByDesc('effective_from')->first()?->amount ?? 0);
        $recurring = $this->salaryCredit->recurringTotal($date);

        if ($recurring > 0 && round($amount - $expected, 2) >= $recurring) {
            $expected = round($expected + $recurring, 2);
        }

        $diff = round($amount - $expected, 2);

        $this->result->salary = [
            'date' => $date,
            'received' => round($amount, 2),
            'expected' => $expected,
            'difference' => $diff,
        ];

        if ($diff > 0) {
            $this->line('  ↑ Salário — recebido R$ '.$this->money($amount).' / previsto R$ '.$this->money($expected).' → +R$ '.$this->money($diff).' em receita variável');

            return [
                'model' => VariableIncome::class,
                'date' => $date,
                'amount' => $diff,
                'attributes' => ['description' => 'Ajuste Salário', 'amount' => $diff, 'received_at' => $date],
                'identifier' => $identifier,
            ];
        }

        if ($diff < 0) {
            $this->line('  ↓ Salário — recebido R$ '.$this->money($amount).' / previsto R$ '.$this->money($expected).' → faltou R$ '.$this->money(abs($diff)).' em despesa variável');

            return [
                'model' => VariableExpense::class,
                'date' => $date,
                'amount' => abs($diff),
                'attributes' => ['description' => 'Ajuste Salário', 'amount' => abs($diff), 'paid_at' => $date],
                'identifier' => $identifier,
            ];
        }

        $this->line('  ✓ Salário confirmado: R$ '.$this->money($amount)." em {$date}");

        return null;
    }

    /** Diferença entre o valor pago e o previsto em fixed_expenses vira despesa/receita variável. */
    private function fixedExpenseAdjustment(array $fixedMatch, float $amount, string $date, string $identifier): ?array
    {
        ['label' => $label, 'db_description' => $dbDescription] = $fixedMatch;

        if ($dbDescription === null) {
            $this->result->fixedExpenses[] = [
                'label' => $label,
                'date' => $date,
                'paid' => round(abs($amount), 2),
                'expected' => null,
                'difference' => null,
            ];

            $this->line("  ✓ {$label} confirmado: R$ ".$this->money(abs($amount))." em {$date}");
            $this->recordPayment($label, $date, $amount, $identifier, creditCardName: $fixedMatch['credit_card'] ?? null);

            return null;
        }

        $fixedExpense = FixedExpense::where('description', $dbDescription)->where('is_active', true)->first();

        if (! $fixedExpense) {
            return null;
        }

        $this->recordPayment($label, $date, $amount, $identifier, fixedExpense: $fixedExpense);

        $actual = abs($amount);
        $expected = (float) $fixedExpense->amount;
        $diff = round($actual - $expected, 2);

        $this->result->fixedExpenses[] = [
            'label' => $label,
            'date' => $date,
            'paid' => $actual,
            'expected' => $expected,
            'difference' => $diff,
        ];

        if ($diff > 0) {
            $this->line("  ↑ {$label} — pago R$ ".$this->money($actual).' / previsto R$ '.$this->money($expected).' → +R$ '.$this->money($diff).' em despesa variável');

            return [
                'model' => VariableExpense::class,
                'date' => $date,
                'amount' => $diff,
                'attributes' => ['description' => "Ajuste {$label}", 'amount' => $diff, 'paid_at' => $date],
                'identifier' => $identifier,
            ];
        }

        if ($diff < 0) {
            $this->line("  ↓ {$label} — pago R$ ".$this->money($actual).' / previsto R$ '.$this->money($expected).' → economia R$ '.$this->money(abs($diff)).' em receita variável');

            return [
                'model' => VariableIncome::class,
                'date' => $date,
                'amount' => abs($diff),
                'attributes' => ['description' => "Ajuste {$label}", 'amount' => abs($diff), 'received_at' => $date],
                'identifier' => $identifier,
            ];
        }

        $this->line("  ✓ {$label} confirmado: R$ ".$this->money($actual)." em {$date}");

        return null;
    }

    /**
     * Insere apenas o que falta: para cada (modelo, data, valor), compara quantos
     * lançamentos a API traz com quantos já existem no banco — vindos do CSV ou de
     * uma execução anterior — e grava só a diferença.
     */
    private function reconcile(array $candidates): void
    {
        $groups = [];

        foreach ($candidates as $candidate) {
            $key = $candidate['model'].'|'.$candidate['date'].'|'.number_format($candidate['amount'], 2, '.', '');
            $groups[$key][] = $candidate;
        }

        foreach ($groups as $key => $group) {
            [$model, $date, $amount] = explode('|', $key);
            $dateColumn = $model === VariableExpense::class ? 'paid_at' : 'received_at';

            $existing = $model::whereDate($dateColumn, $date)->where('amount', $amount)->count();
            $deficit = count($group) - $existing;

            if ($deficit <= 0) {
                $this->result->skipped += count($group);

                continue;
            }

            $alreadyImported = $model::whereIn('identifier', array_column($group, 'identifier'))
                ->pluck('identifier')
                ->all();

            $pending = array_values(array_filter(
                $group,
                fn (array $candidate) => ! in_array($candidate['identifier'], $alreadyImported, true)
            ));

            $this->result->skipped += count($group) - count($pending);

            foreach (array_slice($pending, 0, $deficit) as $candidate) {
                if (! $this->options->dryRun) {
                    $model::create($candidate['attributes'] + ['identifier' => $candidate['identifier']]);
                }

                $this->result->entries[] = [
                    'type' => $model === VariableExpense::class ? 'expense' : 'income',
                    'date' => $date,
                    'amount' => (float) $candidate['amount'],
                    'description' => $candidate['attributes']['description'],
                ];

                $label = $model === VariableExpense::class ? 'despesa' : 'receita';
                $this->line("  + {$label} {$date} R$ ".$this->money($candidate['amount']).' — '.$candidate['attributes']['description']);
                $this->result->created++;
            }

            $this->result->skipped += max(0, count($pending) - $deficit);
        }

        $this->blank();
        $this->info("✅ {$this->result->created} lançamentos gravados.");
        $this->line("⏭  {$this->result->skipped} já existiam (CSV ou importação anterior).");
    }

    /**
     * No Nubank, cada aplicação em caixinha é uma posição de CDB própria, com a taxa
     * real (100%, 115% do CDI), o principal aplicado (amountOriginal) e a data da
     * aplicação. O casamento com investment_boxes é feito por essa taxa.
     *
     * Só as posições ainda ativas são gravadas, uma linha de depósito cada: assim
     * currentBalance() reproduz o principal real e calculateYield() projeta o
     * rendimento a partir da data de cada aplicação. Reconstruir o histórico de
     * BUY/SELL não serve — os resgates carregam o rendimento junto e o principal
     * derrapa (115%: R$ 3.291,90 pelo histórico vs R$ 3.690,04 real).
     */
    private function syncInvestments(string $itemId): float
    {
        $this->info('Caixinhas:');

        $boxesBalance = 0.0;

        $boxes = InvestmentBox::where('is_active', true)->get()
            ->keyBy(fn (InvestmentBox $box) => (string) (float) $box->rate_percentage);

        $candidates = [];
        $unknownRates = [];

        foreach ($this->pluggy->listInvestments($itemId) as $position) {
            $balance = (float) ($position['balance'] ?? 0);

            if ($balance <= 0) {
                continue;
            }

            // Entra no saldo real mesmo sem caixinha cadastrada: o dinheiro está lá.
            $boxesBalance += $balance;

            $rate = (string) (float) ($position['rate'] ?? 0);

            if (! isset($boxes[$rate])) {
                $unknownRates[$rate] = ($unknownRates[$rate] ?? 0) + $balance;

                continue;
            }

            $principal = round((float) ($position['amountOriginal'] ?? $balance), 2);
            $purchasedAt = Carbon::parse($position['purchaseDate'] ?? $position['issueDate'] ?? $position['date']);

            $candidates[] = [
                'investment_box_id' => $boxes[$rate]->id,
                'type' => 'deposit',
                'amount' => $principal,
                'transacted_at' => $purchasedAt->toDateString(),
                'notes' => 'Pluggy — saldo real R$ '.$this->money($balance),
                'identifier' => 'pluggy:position:'.$position['id'],
            ];
        }

        foreach ($unknownRates as $rate => $balance) {
            $this->result->unknownRates[] = ['rate' => $rate, 'balance' => round($balance, 2)];
            $this->warn('  ⚠ R$ '.$this->money($balance)." em posições de {$rate}% CDI sem caixinha cadastrada — ignorado.");
        }

        $existing = InvestmentTransaction::count();

        $this->result->activePositions = count($candidates);
        $this->result->storedMovements = $existing;
        // Compara antes de qualquer regravação: depois do delete o banco seria
        // igual à API por construção, e a divergência que motivou a regravação
        // não teria mais como ser mostrada.
        $this->result->investmentBoxes = $this->compareInvestmentBoxes($candidates, $boxes);

        if (! $this->options->replaceInvestments) {
            $this->line('  '.count($candidates).' posições ativas na API, '.$existing.' movimentações no banco.');
            $this->reportInvestmentDivergence();
            $this->line('  Ative a regravação das caixinhas para substituí-las pelos dados reais.');

            return $boxesBalance;
        }

        if (! $this->options->dryRun) {
            InvestmentTransaction::query()->delete();

            foreach ($candidates as $candidate) {
                InvestmentTransaction::create($candidate);
            }
        }

        $this->result->replacedInvestments = true;
        $this->info("  ✅ {$existing} movimentações substituídas por ".count($candidates).' posições reais.');

        return $boxesBalance;
    }

    /** Compara o principal por caixinha: o que está no banco hoje vs o que a API mostra. */
    private function compareInvestmentBoxes(array $candidates, $boxes): array
    {
        $apiByBox = [];

        foreach ($candidates as $candidate) {
            $apiByBox[$candidate['investment_box_id']] = ($apiByBox[$candidate['investment_box_id']] ?? 0) + $candidate['amount'];
        }

        $comparison = [];

        foreach ($boxes as $box) {
            $api = round($apiByBox[$box->id] ?? 0, 2);
            $db = round($this->investments->currentBalance($box->load('transactions')), 2);

            $comparison[] = [
                'name' => $box->name,
                'database' => $db,
                'api' => $api,
                'matches' => abs($api - $db) < 0.01,
            ];
        }

        return $comparison;
    }

    private function reportInvestmentDivergence(): void
    {
        foreach ($this->result->investmentBoxes as $box) {
            if ($box['matches']) {
                $this->line("  ✓ {$box['name']}: R$ ".$this->money($box['database']));

                continue;
            }

            $this->warn("  ≠ {$box['name']}: banco R$ ".$this->money($box['database']).' / real R$ '.$this->money($box['api']));
        }
    }

    private function money(float $value): string
    {
        return number_format($value, 2, ',', '.');
    }

    private function info(string $text): void
    {
        $this->emit(NubankImportMessage::INFO, $text);
    }

    private function line(string $text): void
    {
        $this->emit(NubankImportMessage::LINE, $text);
    }

    private function warn(string $text): void
    {
        $this->emit(NubankImportMessage::WARN, $text);
    }

    private function blank(): void
    {
        $this->emit(NubankImportMessage::LINE, '');
    }

    /** Guarda a linha no resultado e, se houver ouvinte, entrega na hora. */
    private function emit(string $level, string $text): void
    {
        $message = new NubankImportMessage($level, $text);

        $this->result->add($message);

        if ($this->onMessage !== null) {
            ($this->onMessage)($message);
        }
    }
}
