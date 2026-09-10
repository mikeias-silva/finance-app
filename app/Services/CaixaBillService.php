<?php

namespace App\Services;

use App\Models\CreditCard;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Resolve a fatura do cartão de crédito da Caixa para um mês de vencimento,
 * a partir dos dados do Pluggy. Extraído do controller para que o dashboard
 * também possa comparar a projeção do banco com a da API.
 */
class CaixaBillService
{
    private const CARD_NAME = 'Caixa - Visa';

    public function __construct(
        private PluggyService $pluggy,
        private RecurringChargeDetector $detector,
    ) {}

    /** Fatura completa do mês/ano de vencimento, no mesmo formato exposto pela rota. */
    public function resolve(int $month, int $year, ?string $since = null): array
    {
        $account = collect($this->pluggy->listAccounts(config('pluggy.items.caixa')))
            ->firstWhere('type', 'CREDIT');

        if (! $account) {
            throw new RuntimeException('Cartão de crédito da Caixa não encontrado.');
        }

        $bills = collect($this->pluggy->listBills($account['id']))
            ->sortBy(fn (array $bill) => Carbon::parse($bill['billClosingDate'] ?? $bill['dueDate']))
            ->values();

        $billIndex = $bills->search(function (array $bill) use ($month, $year) {
            $dueDate = Carbon::parse($bill['dueDate']);

            return $dueDate->month === $month && $dueDate->year === $year;
        });

        return $billIndex === false
            ? $this->unreportedBill($account, $bills, $month, $year, $since)
            : $this->reportedBill($account, $bills, $billIndex, $since);
    }

    /** A fatura, ou null se o mês não tiver fatura (mês pulado, ou fora do histórico). */
    public function tryResolve(int $month, int $year): ?array
    {
        try {
            return $this->resolve($month, $year);
        } catch (RuntimeException $e) {
            return null;
        }
    }

    /**
     * Só o total da fatura, ou null se o mês não tiver fatura (mês pulado, ou
     * anterior ao histórico). Usado pelo dashboard, que não precisa do resto.
     */
    public function totalForMonth(int $month, int $year): ?float
    {
        try {
            return (float) $this->resolve($month, $year)['bill']['totalAmount'];
        } catch (RuntimeException $e) {
            return null;
        }
    }

    /** Fatura já publicada pela instituição em GET /bills. */
    private function reportedBill(array $account, Collection $bills, int $billIndex, ?string $since): array
    {
        $bill = $bills[$billIndex];
        $end = Carbon::parse($bill['billClosingDate'] ?? $bill['dueDate'])->endOfDay();

        $previousBill = $billIndex > 0 ? $bills[$billIndex - 1] : null;
        $start = $previousBill
            ? Carbon::parse($previousBill['billClosingDate'] ?? $previousBill['dueDate'])->addDay()->startOfDay()
            : $end->copy()->subMonth()->addDay()->startOfDay();

        $transactions = $this->filterSince(
            $this->pluggy->listTransactions($account['id'], $start, $end),
            $since
        );

        return [
            'bill' => $bill,
            'total_amount_source' => 'api',
            'period' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
            'currency' => $account['currencyCode'],
            'payments' => $this->billPayments($bill['payments'] ?? [], (float) $bill['totalAmount']),
            'transactions_total' => $this->transactionsTotal($transactions),
            'transactions' => $transactions,
        ];
    }

    /**
     * A fatura pedida não está em GET /bills. O vencimento esperado vem da config
     * do cartão (due_day / closing_day / billing_skip_months), e não de "última
     * fatura + 1 mês": quando o vencimento muda, um mês pode ficar sem fatura —
     * é o caso de 07/2026, em que dois ciclos foram acumulados para 01/08.
     *
     * O status distingue dois casos: OPEN, se o ciclo ainda não fechou; e
     * CLOSED_NOT_REPORTED, se já fechou mas a instituição ainda não publicou a
     * fatura no Open Finance.
     */
    private function unreportedBill(array $account, Collection $bills, int $month, int $year, ?string $since): array
    {
        $card = CreditCard::where('name', self::CARD_NAME)->first();
        $requestedMonth = sprintf('%04d-%02d', $year, $month);

        if (in_array($requestedMonth, $card->billing_skip_months ?? [], true)) {
            throw new RuntimeException("O cartão não tem fatura com vencimento em {$requestedMonth}.");
        }

        $lastBill = $bills->last();
        $dueDate = $this->expectedDueDate($card, $month, $year, $lastBill);

        if ($lastBill && $dueDate->lte(Carbon::parse($lastBill['dueDate']))) {
            throw new RuntimeException("Fatura não encontrada para {$month}/{$year}.");
        }

        $closingDate = $this->expectedClosingDate($card, $dueDate);
        $today = now();

        // O ciclo começa no fechamento da fatura anterior — saltando meses sem
        // fatura, para que o ciclo pulado entre nesta. Nunca antes da última
        // fatura já publicada, que delimita o que já foi cobrado.
        $previousDue = $dueDate->copy()->subMonthNoOverflow();

        while (in_array($previousDue->format('Y-m'), $card->billing_skip_months ?? [], true)) {
            $previousDue->subMonthNoOverflow();
        }

        $lastReportedClosing = Carbon::parse($lastBill['billClosingDate'] ?? $lastBill['dueDate']);
        $start = $this->expectedClosingDate($card, $previousDue)
            ->max($lastReportedClosing)
            ->addDay()
            ->startOfDay();

        // Se o ciclo já fechou, o período termina no fechamento; senão, no acumulado até hoje.
        $end = $closingDate->lt($today) ? $closingDate->copy()->endOfDay() : $today->copy()->endOfDay();

        // A fatura não está em /bills, mas os lançamentos trazem em
        // creditCardMetadata.billForecastDate a fatura em que cada um vai cair.
        $forecastMonth = $previousDue->format('Y-m');
        $all = $this->pluggy->listTransactions($account['id'], $start->copy()->subYear(), $today->copy()->addYear());
        $forecast = $this->transactionsForForecast($all, $forecastMonth);

        // Uma fatura detalhada pela instituição traz as parcelas do ciclo. Um grupo
        // sem nenhuma parcela ativa é só o rotativo da fatura anterior repetido,
        // e não serve como previsão desta.
        $isDetailed = $this->countActiveInstallments($forecast) > 0;

        $baseForecast = $isDetailed ? $forecastMonth : $this->latestDetailedForecast($all);
        $baseDue = $this->dueDateForForecast($card, $baseForecast);
        $installments = $this->futureInstallments($all, $baseForecast, $card, $baseDue);

        $match = collect($installments)->firstWhere('due_date', $dueDate->toDateString());
        $projected = round($match['amount'] ?? 0, 2);
        $reported = $this->transactionsTotal($forecast);
        $carryForward = $this->transactionsTotal(array_filter($forecast, fn (array $t) => $this->isCarryForward($t)));
        $recurring = 0.0;

        if ($isDetailed) {
            // A instituição já fechou esta fatura: o total dela é o reportado,
            // rotativo incluído (é assim que bate com a fatura real).
            $totalAmount = $reported;
            $source = 'forecast';
            $transactions = $this->filterSince($forecast, $since);
        } else {
            // Fatura futura: parcelas já contratadas + assinaturas recorrentes que
            // se repetem todo mês + o que a instituição já lançou fora do rotativo
            // (o rotativo é dívida da fatura anterior, não cobrança desta).
            $recurringCharges = $this->detector->detect($all);
            $recurring = round(array_sum(array_column($recurringCharges, 'amount')), 2);

            $totalAmount = round($projected + $recurring + $reported - $carryForward, 2);
            $source = 'installments_projection';
            $transactions = array_merge(
                $match['transactions'] ?? [],
                array_map(fn (array $c) => $this->projectRecurring($c), $recurringCharges),
                array_values(array_filter($forecast, fn (array $t) => ! $this->isCarryForward($t)))
            );
        }

        return [
            'bill' => [
                'id' => null,
                'dueDate' => $dueDate->toDateString(),
                'billClosingDate' => $closingDate->toDateString(),
                'totalAmount' => $totalAmount,
                'totalAmountCurrencyCode' => $account['currencyCode'],
                'payments' => [],
                'status' => $closingDate->lt($today) ? 'CLOSED_NOT_REPORTED' : 'OPEN',
            ],
            'total_amount_source' => $source,
            'total_amount_breakdown' => [
                'reported_by_institution' => $reported,
                'carry_forward' => $carryForward,
                'projected_installments' => $projected,
                'projected_recurring' => $recurring,
            ],
            'forecast_month' => $forecastMonth,
            // A projeção só enxerga o que a instituição já publicou. Quando o app do
            // banco mostra um valor maior, normalmente é lançamento ainda não
            // sincronizado — estes campos deixam a defasagem explícita.
            'data_freshness' => $this->dataFreshness($all, $start),
            'period' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
            'currency' => $account['currencyCode'],
            // Sem objeto de fatura na API, não há pagamentos a listar. Lançamentos
            // negativos do cartão são estornos/ajustes, não pagamentos de fatura.
            'payments' => $this->billPayments([], (float) $totalAmount),
            'account_balance' => (float) $account['balance'],
            'transactions_total' => $this->transactionsTotal($transactions),
            'transactions' => $transactions,
        ];
    }

    /** Soma dos lançamentos — no cartão, positivo é gasto e negativo é estorno. */
    public function transactionsTotal(array $transactions): float
    {
        return round(array_sum(array_map(fn (array $t) => (float) $t['amount'], $transactions)), 2);
    }

    /**
     * Pagamentos da fatura, como vêm em GET /bills (campo payments), com o total
     * quitado e o que resta.
     */
    private function billPayments(array $payments, float $totalAmount): array
    {
        $paid = round(array_sum(array_map(fn (array $p) => (float) $p['amount'], $payments)), 2);

        return [
            'items' => $payments,
            'total_paid' => $paid,
            'remaining' => round($totalAmount - $paid, 2),
            'is_paid' => $paid >= $totalAmount,
        ];
    }

    /** Até onde os dados da instituição chegam, para explicar divergências com o app do banco. */
    private function dataFreshness(array $transactions, Carbon $cycleStart): array
    {
        $inCycle = array_filter(
            $transactions,
            fn (array $t) => Carbon::parse($t['date'])->gte($cycleStart)
        );

        $dates = array_map(fn (array $t) => substr($t['date'], 0, 10), $transactions);

        return [
            'item_last_synced_at' => $this->pluggy->getItem(config('pluggy.items.caixa'))['lastUpdatedAt'] ?? null,
            'latest_transaction_date' => $dates ? max($dates) : null,
            'transactions_in_cycle' => count($inCycle),
        ];
    }

    /** Lançamentos que a instituição prevê para a fatura do mês informado. */
    private function transactionsForForecast(array $transactions, string $forecastMonth): array
    {
        return array_values(array_filter(
            $transactions,
            fn (array $t) => ($t['creditCardMetadata']['billForecastDate'] ?? null) === $forecastMonth
        ));
    }

    /** Linha de resumo do rotativo, não uma compra. */
    private function isCarryForward(array $transaction): bool
    {
        return stripos($transaction['description'] ?? '', 'FATURA ANTERIOR') !== false;
    }

    /**
     * Parcelas já contratadas que cairão nas próximas faturas, derivadas de
     * installmentNumber/totalInstallments (o amount de cada lançamento já é o
     * valor da parcela). A API não lista parcelas futuras como lançamentos, então
     * isto é projeção a partir dos dados dela — não vem pronto do Pluggy.
     */
    private function futureInstallments(array $transactions, ?string $forecastMonth, CreditCard $card, Carbon $baseDue): array
    {
        $byOffset = [];

        foreach ($this->transactionsForForecast($transactions, (string) $forecastMonth) as $transaction) {
            $meta = $transaction['creditCardMetadata'] ?? [];
            $current = (int) ($meta['installmentNumber'] ?? 0);
            $total = (int) ($meta['totalInstallments'] ?? 0);

            if ($total < 2 || $current < 1 || $current >= $total) {
                continue;
            }

            // Cada parcela restante cai numa fatura seguinte, uma por fatura:
            // a de nº 1/5 desta fatura vira 2/5 na próxima, 3/5 na outra, etc.
            for ($offset = 1; $offset <= $total - $current; $offset++) {
                $byOffset[$offset][] = $this->projectInstallment($transaction, $current + $offset);
            }
        }

        ksort($byOffset);

        return collect($byOffset)
            ->map(fn (array $items, int $offset) => [
                'bills_ahead' => $offset,
                'due_date' => $this->dueDateAhead($card, $baseDue, $offset)->toDateString(),
                'amount' => $this->transactionsTotal($items),
                'installments' => count($items),
                'transactions' => $items,
            ])
            ->values()
            ->all();
    }

    /**
     * Copia o lançamento para uma fatura futura, avançando o número da parcela.
     * O valor é o mesmo — no cartão, amount já é o valor da parcela, não da compra.
     */
    private function projectInstallment(array $transaction, int $installmentNumber): array
    {
        $meta = $transaction['creditCardMetadata'] ?? [];

        return [
            'source_transaction_id' => $transaction['id'],
            'description' => $transaction['description'],
            'amount' => (float) $transaction['amount'],
            'currencyCode' => $transaction['currencyCode'],
            'category' => $transaction['category'] ?? null,
            'creditCardMetadata' => [
                'cardNumber' => $meta['cardNumber'] ?? null,
                'purchaseDate' => $meta['purchaseDate'] ?? null,
                'installmentNumber' => $installmentNumber,
                'totalInstallments' => (int) ($meta['totalInstallments'] ?? 0),
            ],
            'projected' => true,
        ];
    }

    /**
     * Cobrança recorrente esperada numa fatura futura. Diferente da parcela, não
     * tem lançamento de origem: é derivada da repetição no histórico.
     */
    private function projectRecurring(array $charge): array
    {
        return [
            'source_transaction_id' => null,
            'description' => $charge['description'],
            'amount' => $charge['amount'],
            'currencyCode' => 'BRL',
            'category' => $charge['category'],
            'creditCardMetadata' => [
                'cardNumber' => $charge['card_number'],
                'purchaseDate' => null,
                'installmentNumber' => null,
                'totalInstallments' => null,
            ],
            'projected' => true,
            'recurring' => true,
            'occurrences' => $charge['occurrences'],
            'regularity_score' => $charge['regularity_score'],
        ];
    }

    /** Quantas parcelas em andamento (nem a última) o grupo contém. */
    private function countActiveInstallments(array $transactions): int
    {
        return collect($transactions)
            ->filter(function (array $t) {
                $meta = $t['creditCardMetadata'] ?? [];
                $current = (int) ($meta['installmentNumber'] ?? 0);
                $total = (int) ($meta['totalInstallments'] ?? 0);

                return $total >= 2 && $current >= 1 && $current < $total;
            })
            ->count();
    }

    /** Último mês de previsão que a instituição detalhou com as parcelas do ciclo. */
    private function latestDetailedForecast(array $transactions): ?string
    {
        return collect($transactions)
            ->groupBy(fn (array $t) => $t['creditCardMetadata']['billForecastDate'] ?? '')
            ->filter(fn ($group, $month) => $month !== '' && $this->countActiveInstallments($group->all()) > 0)
            ->keys()
            ->max();
    }

    /** Vencimento da fatura correspondente a um mês de previsão (mês seguinte, saltando meses sem fatura). */
    private function dueDateForForecast(CreditCard $card, ?string $forecastMonth): Carbon
    {
        $due = Carbon::parse($forecastMonth.'-01')->addMonthNoOverflow();

        while (in_array($due->format('Y-m'), $card->billing_skip_months ?? [], true)) {
            $due->addMonthNoOverflow();
        }

        return $this->expectedDueDate($card, $due->month, $due->year, null);
    }

    /** Caminha N faturas à frente a partir de um vencimento, pulando meses sem fatura. */
    private function dueDateAhead(CreditCard $card, Carbon $baseDue, int $offset): Carbon
    {
        $due = $baseDue->copy();

        for ($i = 0; $i < $offset; $i++) {
            do {
                $due->addMonthNoOverflow();
            } while (in_array($due->format('Y-m'), $card->billing_skip_months ?? [], true));
        }

        return $due;
    }

    /** Vencimento esperado para o mês pedido, pelo due_day do cartão. */
    private function expectedDueDate(CreditCard $card, int $month, int $year, ?array $lastBill): Carbon
    {
        $dueDay = $card->due_day ?? ($lastBill ? Carbon::parse($lastBill['dueDate'])->day : 1);

        return Carbon::create($year, $month, 1)->addDays($dueDay - 1)->startOfDay();
    }

    /** Fechamento do ciclo: se o vencimento é antes do dia de fechamento, fecha no mês anterior. */
    private function expectedClosingDate(CreditCard $card, Carbon $dueDate): Carbon
    {
        $closing = $dueDate->copy()->startOfMonth()->addDays($card->closing_day - 1);

        return $closing->gte($dueDate) ? $closing->subMonthNoOverflow() : $closing;
    }

    /** Filtra lançamentos com data estritamente posterior a $since (fim do dia), se informado. */
    private function filterSince(array $transactions, ?string $since): array
    {
        if (! $since) {
            return $transactions;
        }

        $sinceDate = Carbon::parse($since)->endOfDay();

        return array_values(array_filter(
            $transactions,
            fn (array $t) => Carbon::parse($t['date'])->greaterThan($sinceDate)
        ));
    }
}
