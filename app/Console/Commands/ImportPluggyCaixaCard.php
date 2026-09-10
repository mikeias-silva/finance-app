<?php

namespace App\Console\Commands;

use App\Models\CreditCard;
use App\Models\CreditCardPurchase;
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
        {--from= : Traz o que apareceu na API a partir desta data (padrão: ontem)}
        {--to= : Traz o que apareceu na API até esta data (padrão: hoje)}
        {--all : Importa todo o histórico disponível, ignorando o período}
        {--dry-run : Mostra o que seria gravado, sem gravar}';

    protected $description = 'Importa as compras do cartão de crédito da Caixa pela API do Pluggy';

    private const CARD_NAME = 'Caixa - Visa';

    public function handle(PluggyService $pluggy, RecurringChargeDetector $detector): int
    {
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

        $purchases = $this->purchasesFrom($transactions, $from, $to, $recorrentes);

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
     * Por padrão, o que entrou na API desde ontem.
     */
    private function period(): array
    {
        if ($this->option('all')) {
            return [now()->subYears(2)->startOfDay(), now()->endOfDay()];
        }

        return [
            Carbon::parse($this->option('from') ?? now()->subDay()->toDateString())->startOfDay(),
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
     */
    private function import(Collection $purchases, CreditCard $card, bool $dryRun): void
    {
        $existentes = CreditCardPurchase::where('credit_card_id', $card->id)
            ->get()
            ->map(fn (CreditCardPurchase $p) => $this->key(
                Carbon::parse($p->purchased_at)->toDateString(),
                round((float) $p->total_amount / max(1, (int) $p->installments), 2),
                max(1, (int) $p->installments)
            ))
            ->flip();

        $criadas = 0;
        $ignoradas = 0;

        foreach ($purchases as $p) {
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
        $this->info("✅ {$criadas} compra(s) importada(s).");
        $this->line("⏭  {$ignoradas} já existia(m) no banco.");
    }

    private function key(string $date, float $amount, int $installments): string
    {
        return $date.'|'.number_format($amount, 2, '.', '').'|'.$installments;
    }
}
