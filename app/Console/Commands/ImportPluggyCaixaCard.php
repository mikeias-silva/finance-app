<?php

namespace App\Console\Commands;

use App\Models\CreditCard;
use App\Models\CreditCardPurchase;
use App\Services\DescriptionMatcher;
use App\Services\PluggyService;
use App\Services\RecurringChargeDetector;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Importa as compras do cartão de crédito da Caixa a partir da API do Pluggy
 * para credit_card_purchases, equivalente ao que import:pluggy-nubank faz com a
 * movimentação da conta.
 *
 * A API entrega uma linha por parcela cobrada, não por compra: uma compra em 5x
 * aparece como 1/5 numa fatura, 2/5 na seguinte, e assim por diante. Como cada
 * uma dessas linhas carrega purchaseDate e totalInstallments, qualquer uma delas
 * reconstrói a mesma compra — por isso a deduplicação é essencial, senão a mesma
 * compra entraria uma vez por parcela.
 */
class ImportPluggyCaixaCard extends Command
{
    protected $signature = 'import:pluggy-caixa-card
        {--from= : Traz o que apareceu na API a partir desta data (padrão: 7 dias atrás)}
        {--to= : Traz o que apareceu na API até esta data (padrão: hoje)}
        {--all : Importa todo o histórico disponível, ignorando o período}
        {--purchased-from= : Ignora compras anteriores a esta data (padrão: início da sincronização)}
        {--dry-run : Mostra o que seria gravado, sem gravar}';

    protected $description = 'Importa as compras do cartão de crédito da Caixa pela API do Pluggy';

    private const CARD_NAME = 'Caixa - Visa';

    /** Compras anteriores a esta data ficam como estão, mesmo as que faltam. */
    private const SYNC_START = '2026-10-01';

    /** Quanto o valor digitado à mão pode errar e ainda ser a mesma compra. */
    private const VALUE_TOLERANCE = 5.00;

    private const DATE_TOLERANCE_DAYS = 5;

    /** Sem nome parecido nem parcelas para confirmar, a margem é bem menor. */
    private const UNNAMED_VALUE_TOLERANCE = 1.00;

    private const UNNAMED_DATE_TOLERANCE_DAYS = 1;

    /** Janela padrão: cobre dias em que a execução diária tenha falhado. */
    private const DEFAULT_WINDOW_DAYS = 7;

    private DescriptionMatcher $matcher;

    public function handle(PluggyService $pluggy, RecurringChargeDetector $detector, DescriptionMatcher $matcher): int
    {
        $this->matcher = $matcher;

        $card = CreditCard::where('name', self::CARD_NAME)->first();

        if (! $card) {
            $this->error('Cartão "'.self::CARD_NAME.'" não cadastrado.');

            return self::FAILURE;
        }

        $itemId = config('pluggy.items.caixa');

        if (! $itemId) {
            $this->error('PLUGGY_ITEM_ID_CAIXA não configurado no .env.');

            return self::FAILURE;
        }

        $account = collect($pluggy->listAccounts($itemId))->firstWhere('type', 'CREDIT');

        if (! $account) {
            $this->error('Cartão de crédito da Caixa não encontrado na API.');

            return self::FAILURE;
        }

        [$from, $to] = $this->period();
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Modo dry-run: nada será gravado.');
        }

        $this->line('Sincronização do item: '.($pluggy->getItem($itemId)['lastUpdatedAt'] ?? 'desconhecida'));
        $this->info($this->option('all')
            ? 'Período: todo o histórico'
            : "Apareceu na API entre {$from->toDateString()} e {$to->toDateString()}");
        $this->newLine();

        // Busca larga: a data da transação é a do fechamento da fatura, não a da
        // compra, então filtrar pela janela na API deixaria compras de fora.
        $transactions = $pluggy->listTransactions(
            $account['id'],
            now()->subYears(2),
            now()->addDay()
        );

        // Assinaturas já cadastradas como recorrentes ficam de fora: a projeção
        // as repete todo mês por conta própria, e importá-las como compra avulsa
        // contaria o gasto duas vezes. Os nomes divergem entre as duas origens
        // ("Gympass" no banco é "Wellhub" na API), então o casamento é por valor.
        $recorrentes = $this->recurringAmounts($detector->detect($transactions), $card);

        if ($recorrentes->isNotEmpty()) {
            $this->line('Ignorando recorrentes já cadastradas: R$ '
                .$recorrentes->map(fn ($v) => number_format($v, 2, ',', '.'))->implode(' · R$ '));
        }

        // Assinaturas mudam de valor e de dia, então o detector não as reconhece:
        // quem as identifica é o nome cadastrado.
        $assinaturas = CreditCardPurchase::where('credit_card_id', $card->id)->where('is_recurring', true)->get();
        [$cobrancas, $demais] = collect($transactions)->partition(
            fn (array $t) => $this->subscriptionFor($t, $assinaturas) !== null
        );

        $purchasedFrom = Carbon::parse($this->option('purchased-from') ?? self::SYNC_START)->startOfDay();

        $this->adjustSubscriptions($cobrancas, $assinaturas, $card, $purchasedFrom, $dryRun);

        $purchases = $this->purchasesFrom($demais->all(), $from, $to, $recorrentes)
            ->filter(fn (array $p) => $p['purchased_at'] >= $purchasedFrom->toDateString())
            ->values();

        if ($purchases->isEmpty()) {
            $this->comment('Nenhuma compra encontrada no período.');
            $this->line('A Caixa costuma publicar as compras só no fechamento da fatura,');
            $this->line('então compras recentes podem ainda não estar na API.');

            return self::SUCCESS;
        }

        $this->import($purchases, $card, $dryRun);

        return self::SUCCESS;
    }

    /**
     * Janela de quando o lançamento apareceu na API — não da data da compra.
     * A Caixa publica em bloco no fechamento da fatura, então filtrar pela data
     * da compra traria lançamentos antigos toda vez que a fatura fechasse.
     * Por padrão, os últimos dias: repetir a janela é seguro, porque o que já
     * existe não entra de novo.
     */
    private function period(): array
    {
        if ($this->option('all')) {
            return [now()->subYears(2)->startOfDay(), now()->endOfDay()];
        }

        return [
            Carbon::parse($this->option('from') ?? now()->subDays(self::DEFAULT_WINDOW_DAYS)->toDateString())->startOfDay(),
            Carbon::parse($this->option('to') ?? now()->toDateString())->endOfDay(),
        ];
    }

    /**
     * Reduz os lançamentos a compras distintas. Estornos, pagamentos e a linha de
     * rotativo não são compras; parcelas da mesma compra colapsam numa só.
     */
    private function purchasesFrom(array $transactions, Carbon $from, Carbon $to, Collection $recorrentes): Collection
    {
        return collect($transactions)
            ->filter(function (array $t) use ($from, $to, $recorrentes) {
                if ((float) $t['amount'] <= 0) {
                    return false; // estorno ou ajuste de crédito
                }

                if ($this->isBillArtifact($t['description'] ?? '')) {
                    return false;
                }

                $valor = round((float) $t['amount'], 2);

                if ($recorrentes->contains(fn (float $r) => abs($r - $valor) < 0.01)) {
                    return false;
                }

                if (! $this->purchaseDate($t)) {
                    return false;
                }

                // A janela é sobre quando o Pluggy registrou o lançamento.
                $apareceuEm = Carbon::parse($t['createdAt'] ?? $t['date']);

                return $apareceuEm->betweenIncluded($from, $to);
            })
            ->map(fn (array $t) => [
                'description' => $t['description'],
                'purchased_at' => $this->purchaseDate($t)->toDateString(),
                'installments' => max(1, (int) ($t['creditCardMetadata']['totalInstallments'] ?? 1)),
                'installment_amount' => round((float) $t['amount'], 2),
            ])
            // Parcelas da mesma compra viram uma linha só.
            ->unique(fn (array $p) => $this->key($p['purchased_at'], $p['installment_amount'], $p['installments']))
            ->sortBy('purchased_at')
            ->values();
    }

    /**
     * Valores das cobranças recorrentes que já têm cadastro no cartão. Só entram
     * as que a API detecta como recorrentes E que existem no banco marcadas como
     * tal — assim uma assinatura ainda não cadastrada continua sendo importada.
     */
    private function recurringAmounts(array $detectadas, CreditCard $card): Collection
    {
        $cadastradas = CreditCardPurchase::where('credit_card_id', $card->id)
            ->where('is_recurring', true)
            ->pluck('total_amount')
            ->map(fn ($v) => round((float) $v, 2));

        return collect($detectadas)
            ->pluck('amount')
            ->map(fn ($v) => round((float) $v, 2))
            ->filter(fn (float $api) => $cadastradas->contains(
                // Tolerância de R$ 1,50: o cadastro manual costuma ter o valor
                // arredondado (Gympass 89,00 no banco, Wellhub 89,99 na API).
                fn (float $db) => abs($db - $api) <= 1.50
            ))
            ->unique()
            ->values();
    }

    /**
     * Linhas de fatura que não representam compra: pagamentos, estornos, ajustes,
     * o rotativo e tarifas do cartão. A anuidade entra aqui porque a API informa
     * como data dela o fechamento da fatura, não a contratação — importá-la
     * criaria uma compra nova a cada ciclo.
     */
    private function isBillArtifact(string $description): bool
    {
        foreach (['FATURA ANTERIOR', 'PGTO', 'PAGAMENTO', 'AJUSTE', 'CASHBACK', 'ESTORNO', 'ANUIDADE'] as $termo) {
            if (stripos($description, $termo) !== false) {
                return true;
            }
        }

        return false;
    }

    private function purchaseDate(array $transaction): ?Carbon
    {
        $date = $transaction['creditCardMetadata']['purchaseDate'] ?? $transaction['date'] ?? null;

        return $date ? Carbon::parse($date)->startOfDay() : null;
    }

    /**
     * Grava o que ainda não existe. A comparação é por data da compra + valor da
     * parcela + número de parcelas: se já houver uma compra com essa combinação,
     * é a mesma compra e não entra de novo — mesma data e mesmo valor não são
     * duas compras distintas.
     *
     * O que não bate exatamente passa pelo cadastro manual: sendo a mesma compra
     * digitada com outro valor ou data, ela é corrigida com o que veio do Pluggy
     * em vez de duplicada. Cada lançamento manual só absorve uma compra.
     */
    private function import(Collection $purchases, CreditCard $card, bool $dryRun): void
    {
        $cadastradas = CreditCardPurchase::where('credit_card_id', $card->id)->get();

        $existentes = $cadastradas->map(fn (CreditCardPurchase $p) => $this->storedKey($p))->flip();

        $chavesDaApi = $purchases
            ->map(fn (array $p) => $this->key($p['purchased_at'], $p['installment_amount'], $p['installments']))
            ->flip();

        $manuais = $cadastradas
            ->reject(fn (CreditCardPurchase $p) => $p->is_recurring
                || CreditCardPurchase::isAdjustment($p->description)
                || $chavesDaApi->has($this->storedKey($p)))
            ->keyBy('id');

        $pares = $this->pairManuals($purchases, $manuais, $existentes);

        $criadas = 0;
        $ignoradas = 0;
        $corrigidas = 0;

        foreach ($purchases as $index => $p) {
            $chave = $this->key($p['purchased_at'], $p['installment_amount'], $p['installments']);

            if ($existentes->has($chave)) {
                $ignoradas++;
                $this->line(sprintf('  = %s  %10s  %dx  %s',
                    $p['purchased_at'],
                    number_format($p['installment_amount'], 2, ',', '.'),
                    $p['installments'],
                    mb_strimwidth($p['description'], 0, 34, '…')
                ));

                continue;
            }

            $total = round($p['installment_amount'] * $p['installments'], 2);

            $manual = $pares->get($index);

            if ($manual !== null) {
                $existentes->put($chave, true);
                $corrigidas++;

                $this->line(sprintf('  <comment>~</comment> %s %s %dx  %s  →  %s %s %dx  (%s)',
                    Carbon::parse($manual->purchased_at)->format('d/m'),
                    number_format((float) $manual->total_amount, 2, ',', '.'),
                    $manual->installments,
                    mb_strimwidth($manual->description, 0, 24, '…'),
                    Carbon::parse($p['purchased_at'])->format('d/m'),
                    number_format($total, 2, ',', '.'),
                    $p['installments'],
                    mb_strimwidth($p['description'], 0, 28, '…')
                ));

                if (! $dryRun) {
                    $manual->update([
                        'total_amount' => $total,
                        'installments' => $p['installments'],
                        'purchased_at' => $p['purchased_at'],
                    ]);
                }

                continue;
            }

            if (! $dryRun) {
                CreditCardPurchase::create([
                    'credit_card_id' => $card->id,
                    'description' => $p['description'],
                    'total_amount' => $total,
                    'installments' => $p['installments'],
                    'purchased_at' => $p['purchased_at'],
                ]);
            }

            // Registra a chave para que uma repetição na mesma execução não entre duas vezes.
            $existentes->put($chave, true);

            $criadas++;
            $this->line(sprintf('  <info>+</info> %s  %10s  %dx  (total %s)  %s',
                $p['purchased_at'],
                number_format($p['installment_amount'], 2, ',', '.'),
                $p['installments'],
                number_format($total, 2, ',', '.'),
                mb_strimwidth($p['description'], 0, 34, '…')
            ));
        }

        $this->newLine();
        $this->info($dryRun ? "✅ {$criadas} compra(s) seriam importada(s)." : "✅ {$criadas} compra(s) importada(s).");
        $this->line($dryRun ? "🔧 {$corrigidas} seriam corrigida(s) pelo Pluggy." : "🔧 {$corrigidas} corrigida(s) pelo Pluggy.");
        $this->line("⏭  {$ignoradas} já existia(m) no banco.");
    }

    /**
     * Associa cada compra do Pluggy a uma compra manual, uma só para uma só. Em
     * duas passadas: primeiro os casamentos confirmados (nome ou parcelamento),
     * depois os de valor e data quase exatos. Assim uma compra de valor parecido
     * sem nome em comum (Aguinaldo 16,20 em 01/09) não rouba a linha manual de
     * outra compra confirmada (matte 15,30 em 03/09).
     *
     * @return Collection<int, CreditCardPurchase> chaveada pelo índice da compra da API
     */
    private function pairManuals(Collection $purchases, Collection $manuais, Collection $existentes): Collection
    {
        $pares = collect();
        $disponiveis = $manuais;

        foreach ([true, false] as $soConfirmados) {
            foreach ($purchases as $index => $p) {
                $chave = $this->key($p['purchased_at'], $p['installment_amount'], $p['installments']);

                if ($pares->has($index) || $existentes->has($chave)) {
                    continue;
                }

                $total = round($p['installment_amount'] * $p['installments'], 2);
                $manual = $this->sameManualPurchase($p, $total, $disponiveis, $soConfirmados);

                if ($manual !== null) {
                    $pares->put($index, $manual);
                    $disponiveis = $disponiveis->except($manual->id);
                }
            }
        }

        return $pares;
    }

    /** A compra manual que corresponde a esta, a mais próxima se houver mais de uma. */
    private function sameManualPurchase(array $purchase, float $total, Collection $manuais, bool $soConfirmados = false): ?CreditCardPurchase
    {
        return $manuais
            ->filter(fn (CreditCardPurchase $manual) => $this->isSamePurchase($purchase, $total, $manual, $soConfirmados))
            ->sortBy(fn (CreditCardPurchase $manual) => abs((float) $manual->total_amount - $total)
                + $this->daysApart($purchase['purchased_at'], $manual))
            ->first();
    }

    /**
     * Valor e data próximos não bastam: duas compras de valor parecido em dias
     * vizinhos são comuns. Precisa de uma confirmação — nome parecido, ou o mesmo
     * parcelamento, que resolve quando a descrição manual é outra ("hotel RJ" e
     * "EBN *NOMAD"). Sem nenhuma das duas, só uma coincidência quase exata conta.
     */
    private function isSamePurchase(array $purchase, float $total, CreditCardPurchase $manual, bool $soConfirmados = false): bool
    {
        $valueGap = round(abs((float) $manual->total_amount - $total), 2);
        $days = $this->daysApart($purchase['purchased_at'], $manual);

        $confirmed = $this->matcher->similar($manual->description, $purchase['description'])
            || ($purchase['installments'] > 1 && $purchase['installments'] === (int) $manual->installments);

        if ($soConfirmados && ! $confirmed) {
            return false;
        }

        return $confirmed
            ? $valueGap <= self::VALUE_TOLERANCE && $days <= self::DATE_TOLERANCE_DAYS
            : $valueGap <= self::UNNAMED_VALUE_TOLERANCE && $days <= self::UNNAMED_DATE_TOLERANCE_DAYS;
    }

    private function daysApart(string $date, CreditCardPurchase $manual): int
    {
        return (int) abs(Carbon::parse($date)->startOfDay()->diffInDays(Carbon::parse($manual->purchased_at)->startOfDay()));
    }

    /** A assinatura cadastrada a que este lançamento pertence, se houver. */
    private function subscriptionFor(array $transaction, Collection $assinaturas): ?CreditCardPurchase
    {
        if ((float) $transaction['amount'] <= 0) {
            return null;
        }

        return $assinaturas->first(fn (CreditCardPurchase $assinatura) => $this->matcher->containsAllWords(
            $assinatura->description,
            $transaction['description'] ?? ''
        ));
    }

    /**
     * A assinatura segue com o valor cadastrado e só a diferença do mês vira
     * lançamento, como nas contas fixas. Fica no cartão, e não como despesa da
     * conta, para cair na fatura da cobrança real. Rodar de novo atualiza o mesmo
     * ajuste. Um lançamento pendente e sua versão confirmada contam uma vez só.
     */
    private function adjustSubscriptions(Collection $cobrancas, Collection $assinaturas, CreditCard $card, Carbon $since, bool $dryRun): void
    {
        foreach ($assinaturas as $assinatura) {
            $inicio = Carbon::parse($assinatura->purchased_at)->startOfDay();

            $cobrancas
                ->filter(fn (array $t) => $this->subscriptionFor($t, $assinaturas)?->is($assinatura))
                ->filter(fn (array $t) => Carbon::parse($t['date'])->gte($inicio) && Carbon::parse($t['date'])->gte($since))
                ->unique(fn (array $t) => substr($t['date'], 0, 10).'|'.number_format((float) $t['amount'], 2, '.', ''))
                ->groupBy(fn (array $t) => Carbon::parse($t['date'])->format('Y-m'))
                ->each(fn (Collection $mes) => $this->adjustMonth($mes, $assinatura, $card, $dryRun));
        }
    }

    private function adjustMonth(Collection $lines, CreditCardPurchase $assinatura, CreditCard $card, bool $dryRun): void
    {
        $date = Carbon::parse($lines->first()['date']);
        $paid = round($lines->sum(fn (array $t) => (float) $t['amount']), 2);
        $difference = round($paid - (float) $assinatura->total_amount, 2);
        $description = CreditCardPurchase::ADJUSTMENT_PREFIX.$assinatura->description;

        $ajuste = CreditCardPurchase::where('credit_card_id', $card->id)
            ->where('description', $description)
            ->whereBetween('purchased_at', [$date->copy()->startOfMonth()->toDateString(), $date->copy()->endOfMonth()->toDateString()])
            ->first();

        $this->line(sprintf('  * %s  %s: cobrado %s, cadastrado %s, ajuste %s',
            $date->format('m/Y'),
            $assinatura->description,
            number_format($paid, 2, ',', '.'),
            number_format((float) $assinatura->total_amount, 2, ',', '.'),
            number_format($difference, 2, ',', '.')
        ));

        if ($dryRun) {
            return;
        }

        if ($difference === 0.0) {
            $ajuste?->delete();

            return;
        }

        if ($ajuste !== null) {
            $ajuste->update(['total_amount' => $difference]);

            return;
        }

        CreditCardPurchase::create([
            'credit_card_id' => $card->id,
            'description' => $description,
            'total_amount' => $difference,
            'installments' => 1,
            'purchased_at' => $date->toDateString(),
        ]);
    }

    private function storedKey(CreditCardPurchase $purchase): string
    {
        return $this->key(
            Carbon::parse($purchase->purchased_at)->toDateString(),
            round((float) $purchase->total_amount / max(1, (int) $purchase->installments), 2),
            max(1, (int) $purchase->installments)
        );
    }

    private function key(string $date, float $amount, int $installments): string
    {
        return $date.'|'.number_format($amount, 2, '.', '').'|'.$installments;
    }
}
