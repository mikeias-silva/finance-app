<?php

namespace App\Services;

use App\Models\CreditCard;
use App\Models\CreditCardPurchase;
use App\Models\FixedExpense;
use App\Models\InvestmentTransaction;
use App\Models\RecurringIncome;
use App\Models\Salary;
use App\Models\StatementPayment;
use App\Models\VariableExpense;
use App\Models\VariableIncome;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ProjectionService
{
    private const CAIXA_CARD = 'Caixa - Visa';

    public function project(Carbon $from, Carbon $to): Collection
    {
        $months = collect();
        $current = $from->copy()->startOfMonth();

        while ($current->lte($to)) {
            $period = BillingPeriod::for($current);

            $months->put($current->format('Y-m'), [  // put, não push
                'month' => $current->copy(),
                'period_start' => $period['start'],
                'period_end' => $period['end'],
                'card_expenses' => $this->cardExpensesForMonth($current),
                'fixed_expenses' => $this->fixedExpensesForMonth($current),
                'salary' => $this->salaryForMonth($current),
                'variable_income' => $this->variableIncomesForMonth($current),
                'recurring_income' => $this->recurringIncomesForMonth($current),
                'variable_expenses' => $this->variableExpensesForMonth($current),
                'color' => 'gray', // default, será atualizado depois
            ]);
            $current->addMonth();
        }

        return $months->map(function ($m) {
            $total_expenses = $m['card_expenses']->sum('amount')
                + $m['fixed_expenses']->sum('amount')
                + $m['variable_expenses']->sum('amount');
            $total_incomes = $m['salary']
                + $m['variable_income']->sum('amount')
                + $m['recurring_income']->sum('amount');
            $m['balance'] = $total_incomes - $total_expenses;
            $m['rdb_net'] = $this->rdbNetForMonth($m['month']);
            $m['saldo_cc'] = $m['balance'] - $m['rdb_net'];

            return $m;
        });
    }

    /**
     * A fatura da Caixa de um mês, item a item, juntando o que a API já publicou
     * com o que só existe no cadastro manual — sem contar a mesma compra duas
     * vezes. Serve porque a Caixa demora a publicar, e o lançamento manual
     * antecipa a previsão.
     */
    public function mergedCaixaItems(Carbon $month, CaixaBillService $bills): ?array
    {
        $bill = $bills->tryResolve($month->month, $month->year);

        if ($bill === null) {
            return null;
        }

        $fromDb = $this->cardExpensesForMonth($month)
            ->filter(fn (array $e) => $e['card'] === self::CAIXA_CARD);

        $onlyInDb = $this->caixaEntriesMissingFromApi($fromDb, $bill['transactions'])
            ->map(fn (array $e) => [
                'description' => $e['description'],
                'amount' => (float) $e['amount'],
                'currencyCode' => 'BRL',
                'creditCardMetadata' => [
                    'cardNumber' => null,
                    'purchaseDate' => $e['purchased_at'] ?? null,
                    'installmentNumber' => $e['current_installment'] ?? null,
                    'totalInstallments' => $e['installments'] ?? null,
                ],
                'from_db' => true,
            ])
            ->values();

        return [
            'bill' => $bill['bill'],
            'period' => $bill['period'],
            'total_from_api' => round((float) $bill['bill']['totalAmount'], 2),
            'total_only_in_db' => round($onlyInDb->sum('amount'), 2),
            'total' => round((float) $bill['bill']['totalAmount'] + $onlyInDb->sum('amount'), 2),
            'transactions' => array_merge($bill['transactions'], $onlyInDb->all()),
        ];
    }

    /** Lançamentos do cadastro manual que a API ainda não publicou nesta fatura. */
    private function caixaEntriesMissingFromApi(Collection $fromDb, array $apiTransactions): Collection
    {
        $keys = [];
        $amounts = [];

        foreach ($apiTransactions as $transaction) {
            $meta = $transaction['creditCardMetadata'] ?? [];
            $amount = number_format((float) $transaction['amount'], 2, '.', '');
            $amounts[$amount] = ($amounts[$amount] ?? 0) + 1;

            if (! empty($meta['purchaseDate'])) {
                $key = substr($meta['purchaseDate'], 0, 10).'|'.$amount.'|'.($meta['totalInstallments'] ?: 1);
                $keys[$key] = ($keys[$key] ?? 0) + 1;
            }
        }

        return $fromDb->reject(function (array $entry) use (&$keys, &$amounts) {
            // A API projeta as recorrentes por conta própria, com outra descrição.
            if ($entry['is_recurring'] ?? false) {
                return true;
            }

            // O ajuste é a diferença de uma cobrança que a API já traz inteira.
            if (CreditCardPurchase::isAdjustment($entry['description'])) {
                return true;
            }

            $amount = number_format((float) $entry['amount'], 2, '.', '');
            $key = ($entry['purchased_at'] ?? '').'|'.$amount.'|'.($entry['installments'] ?: 1);

            foreach ([[&$keys, $key], [&$amounts, $amount]] as [$bag, $lookup]) {
                if (($bag[$lookup] ?? 0) > 0) {
                    $bag[$lookup]--;

                    return true;
                }
            }

            return false;
        });
    }

    /**
     * Constrói o saldo acumulado a partir de uma âncora real (conta corrente +
     * caixinhas, lidas da API). O ciclo corrente está parcialmente realizado: a
     * âncora já reflete o que saiu/entrou até hoje, então dele só entra o que ainda
     * falta acontecer. Nos meses futuros, nada aconteceu ainda e vale o balance cheio.
     */
    public function accumulate(Collection $projection, float $anchor, ?bool $salaryCredited = null): Collection
    {
        $today = Carbon::today();
        $running = $anchor;
        $investments = app(InvestmentService::class);
        $boxes = $investments->yieldingBoxes();

        return $projection->map(function (array $month) use (&$running, &$boxes, $today, $salaryCredited, $investments) {
            $isCurrentCycle = $today->betweenIncluded($month['period_start'], $month['period_end']);

            $month['pending'] = $isCurrentCycle
                ? $this->pendingForCycle($month, $today, $salaryCredited)
                : $month['balance'];

            // O ciclo corrente só rende daqui em diante: o que já rendeu está na âncora.
            // Fica de fora do resultado do ciclo, que é o que entra menos o que sai.
            $month['yield'] = $investments->accrue($boxes, $month['period_start']->max($today), $month['period_end']->copy()->addDay());

            $running += $month['pending'] + $month['yield'];
            $month['accumulated'] = $running;

            return $month;
        });
    }

    /** O que ainda falta acontecer no ciclo corrente. */
    private function pendingForCycle(array $month, Carbon $today, ?bool $salaryCredited): float
    {
        // A receita recorrente cai no mesmo crédito do salário — mesma data, mesmo
        // lançamento no extrato. Entra e sai da pendência pela mesma regra.
        $creditedWithSalary = (float) $month['salary']
            + (float) $month['recurring_income']->sum('amount');

        $salary = $this->salaryAlreadyReceived($month, $today, $salaryCredited)
            ? 0.0
            : $creditedWithSalary;

        $incomes = $month['variable_income']
            ->filter(fn ($i) => $this->stillToHappen($i['received_at'], $i['identifier'] ?? null, $today))
            ->sum('amount');

        $expenses = $month['variable_expenses']
            ->filter(fn ($e) => $this->stillToHappen($e['paid_at'], $e['identifier'] ?? null, $today))
            ->sum('amount');

        return $salary + $incomes - $expenses
            - $this->unpaidFixedExpenses($month)
            - $this->unpaidCardExpenses($month);
    }

    /**
     * O salário do ciclo já está na âncora? O salário cai no primeiro dia do ciclo,
     * mas o saldo que serve de âncora vem da sincronia diária do Pluggy — no dia em
     * que ele cai, o saldo lido ainda é o de antes. Decidir pela data tira o salário
     * do pendente antes de ele entrar no saldo, e ele some das duas pontas.
     */
    private function salaryAlreadyReceived(array $month, Carbon $today, ?bool $credited): bool
    {
        // Havendo resposta do extrato, ela manda: vem da mesma leitura que a âncora,
        // então as duas nunca discordam sobre o mesmo crédito.
        if ($credited !== null) {
            return $credited;
        }

        // Sem resposta, resta o calendário — mas só a partir do dia seguinte, para
        // que o dia do crédito continue previsto em vez de sumir.
        return $month['period_start']->lt($today);
    }

    /**
     * O lançamento ainda vai acontecer? Data futura, sim. Data passada, não. No dia
     * de hoje, decide a origem: o que veio do extrato (identifier preenchido) já
     * mexeu no saldo real, o que foi cadastrado à mão ainda não.
     */
    private function stillToHappen(mixed $date, ?string $identifier, Carbon $today): bool
    {
        $date = Carbon::parse($date)->startOfDay();

        if (! $date->isSameDay($today)) {
            return $date->gt($today);
        }

        return $identifier === null;
    }

    /** Contas fixas do ciclo que ainda não apareceram no extrato. */
    private function unpaidFixedExpenses(array $month): float
    {
        $paid = $this->paymentsInCycle($month)
            ->pluck('fixedExpense.description')
            ->filter()
            ->unique();

        return (float) $month['fixed_expenses']
            ->reject(fn ($e) => $paid->contains($e['description']))
            ->sum('amount');
    }

    /** Faturas do ciclo que ainda não foram quitadas. */
    private function unpaidCardExpenses(array $month): float
    {
        $paidCards = $this->paymentsInCycle($month)
            ->pluck('creditCard.name')
            ->filter()
            ->unique();

        return (float) $month['card_expenses']
            ->reject(fn ($e) => $paidCards->contains($e['card']))
            ->sum('amount');
    }

    /** Pagamentos identificados no extrato dentro do ciclo do mês. */
    private function paymentsInCycle(array $month): Collection
    {
        return StatementPayment::with('fixedExpense', 'creditCard')
            ->whereBetween('paid_at', [
                $month['period_start']->toDateString(),
                // O cast `date` grava "Y-m-d 00:00:00". Comparar com a data pura
                // deixaria de fora tudo que acontece no último dia do ciclo.
                $month['period_end']->copy()->endOfDay(),
            ])
            ->get();
    }

    public function cardExpensesForMonth(Carbon $month): Collection
    {
        // Compra paga por outra pessoa fica registrada, mas fora de qualquer cálculo.
        $purchases = CreditCardPurchase::with('creditCard')->where('excluded_from_projection', false)->get();

        // Modelo por data de cobrança:
        // Para cada parcela N, chargeDate = purchaseDate + N meses.
        // resolveBillingMonth(chargeDate) indica em qual mês de pagamento ela cai.
        // Na fatura "gorda" (ex: agosto cobre jun/2–jul/22), duas cobranças consecutivas
        // podem cair no mesmo mês de pagamento → a parcela aparece duas vezes.
        return $purchases->flatMap(function ($purchase) use ($month) {
            $card = $purchase->creditCard;
            $purchaseDate = Carbon::parse($purchase->purchased_at);
            $skipMonths = $card->billing_skip_months ?? [];
            $entries = [];

            if ($purchase->is_recurring) {
                // Recorrente: itera meses de cobrança até ultrapassar o mês alvo.
                // Aqui repetir na mesma fatura é correto — a assinatura é cobrada
                // todo mês, e um ciclo longo abarca duas cobranças.
                for ($k = 0; ; $k++) {
                    $chargeDate = $purchaseDate->copy()->addMonths($k);
                    $paymentMonth = $this->resolvePaymentMonth($chargeDate, $card, $skipMonths);

                    if ($paymentMonth->gt($month)) {
                        break;
                    }

                    if ($paymentMonth->format('Y-m') === $month->format('Y-m')) {
                        $entries[] = $this->makeExpenseEntry($purchase, $card, $k + 1);
                    }
                }
            } elseif ($card->name === self::CAIXA_CARD) {
                // A Caixa lança uma parcela por fechamento de fatura. Quando um mês
                // fica sem fatura — como julho/2026, em que o vencimento mudou —
                // as parcelas não se acumulam: a sequência apenas segue para a
                // próxima fatura existente. Vale só para parcelamento; recorrente
                // pode aparecer duas vezes num ciclo longo, tratado acima.
                $installment = $this->caixaOccurrenceInMonth($purchase, $card, $purchaseDate, $month);

                if ($installment !== null) {
                    $entries[] = $this->makeExpenseEntry($purchase, $card, $installment);
                }
            } else {
                // Parcelado: itera cada parcela pela sua data de cobrança real
                for ($n = 0; $n < $purchase->installments; $n++) {
                    $chargeDate = $purchaseDate->copy()->addMonths($n);
                    $paymentMonth = $this->resolvePaymentMonth($chargeDate, $card, $skipMonths);

                    if ($paymentMonth->format('Y-m') === $month->format('Y-m')) {
                        $entries[] = $this->makeExpenseEntry($purchase, $card, $n + 1);
                    }
                }
            }

            return $entries;
        })->values();
    }

    /**
     * Qual parcela desta compra cai na fatura paga no mês informado, ou null se
     * nenhuma. A Caixa lança as parcelas no fechamento da fatura, uma por fatura:
     * a 1ª entra na primeira fatura que fecha a partir da compra, a 2ª na fatura
     * seguinte, e assim por diante. Um mês sem fatura não acumula duas parcelas —
     * apenas desloca a sequência para a próxima fatura que existe.
     */
    private function caixaOccurrenceInMonth(
        CreditCardPurchase $purchase,
        CreditCard $card,
        Carbon $purchaseDate,
        Carbon $month,
    ): ?int {
        $installments = max(1, (int) $purchase->installments);
        $index = 0;

        foreach ($this->caixaBillCycles($card, $purchaseDate, $installments) as $bill) {
            // Compra feita no dia do fechamento já cai na fatura seguinte.
            if ($bill['closing']->lte($purchaseDate)) {
                continue;
            }

            $index++;

            if ($bill['payment_month']->format('Y-m') === $month->format('Y-m')) {
                return $index <= $installments ? $index : null;
            }

            if ($index >= $installments || $bill['payment_month']->gt($month)) {
                return null;
            }
        }

        return null;
    }

    /**
     * Faturas do cartão a partir da compra: data de fechamento e mês de pagamento.
     * Respeita a mudança de fechamento/vencimento (closing_changed_at) e os meses
     * sem fatura (billing_skip_months).
     */
    private function caixaBillCycles(CreditCard $card, Carbon $purchaseDate, int $occurrences): array
    {
        $skip = $card->billing_skip_months ?? [];
        $changedAt = $card->closing_changed_at ? Carbon::parse($card->closing_changed_at) : null;

        $cycles = [];
        $cursor = $purchaseDate->copy()->startOfMonth();
        // Uma volta extra cobre os meses pulados e o deslocamento do vencimento.
        $limit = $occurrences + count($skip) + 2;

        while (count($cycles) < $limit) {
            $usesOld = $changedAt && $cursor->lt($changedAt->copy()->startOfMonth());
            $closingDay = $usesOld ? ($card->old_closing_day ?? $card->closing_day) : $card->closing_day;
            $dueDay = $usesOld ? ($card->old_due_day ?? $card->due_day) : $card->due_day;

            $closing = $cursor->copy()->startOfMonth()->addDays($closingDay - 1);

            // Vencimento antes do fechamento significa pagar no mês seguinte.
            $paymentMonth = ($dueDay !== null && $dueDay < $closingDay)
                ? $cursor->copy()->startOfMonth()->addMonthNoOverflow()
                : $cursor->copy()->startOfMonth();

            if (! in_array($paymentMonth->format('Y-m'), $skip, true)) {
                $cycles[] = ['closing' => $closing, 'payment_month' => $paymentMonth];
            }

            $cursor->addMonthNoOverflow();
        }

        return $cycles;
    }

    public function fixedExpensesForMonth(Carbon $month): Collection
    {
        return FixedExpense::where('is_active', true)->get()
            ->filter(function ($e) use ($month) {
                return is_null($e->valid_until) || $month->lte($e->valid_until->endOfMonth());
            })
            ->map(fn ($e) => ['description' => $e->description, 'amount' => $e->amount]);
    }

    private function salaryForMonth(Carbon $month): float
    {
        $salary = Salary::where('effective_from', '<=', $month)
            ->orderByDesc('effective_from')->first();

        return $salary?->amount ?? 0;

    }

    public function variableExpensesForMonth(Carbon $month): Collection
    {
        $period = BillingPeriod::for($month);

        return VariableExpense::query()
            ->whereBetween('paid_at', [
                $period['start']->toDateString(),
                $period['end']->copy()->endOfDay(),
            ])
            ->get()
            ->map(fn ($e) => ['description' => $e->description, 'amount' => $e->amount, 'paid_at' => $e->paid_at, 'identifier' => $e->identifier]);
    }

    public function variableIncomesForMonth(Carbon $month): Collection
    {
        $period = BillingPeriod::for($month);

        return VariableIncome::query()
            ->whereBetween('received_at', [
                $period['start']->toDateString(),
                $period['end']->copy()->endOfDay(),
            ])
            ->get()
            ->map(fn ($i) => ['description' => $i->description, 'amount' => $i->amount, 'received_at' => $i->received_at, 'identifier' => $i->identifier]);
    }

    /**
     * Receitas que se repetem todo ciclo e caem junto do salário, no primeiro dia
     * do período. A vigência é comparada com esse dia de pagamento, e não com o
     * primeiro dia do mês: a gratificação que estreia em 30/09 pertence ao ciclo de
     * outubro, que é justamente o que começa nessa data.
     */
    public function recurringIncomesForMonth(Carbon $month): Collection
    {
        $paymentDay = BillingPeriod::for($month)['start'];

        return RecurringIncome::paidOn($paymentDay)
            ->get()
            ->map(fn ($i) => [
                'description' => $i->description,
                'amount' => $i->amount,
                'received_at' => $paymentDay->toDateString(),
            ])
            ->values();
    }

    public function rdbNetForMonth(Carbon $month): float
    {
        $period = BillingPeriod::for($month);

        $transactions = InvestmentTransaction::whereBetween('transacted_at', [
            $period['start']->toDateString(),
            $period['end']->copy()->endOfDay(),
        ])->get();

        $deposits = $transactions->where('type', 'deposit')->sum('amount');
        $withdrawals = $transactions->where('type', 'withdrawal')->sum('amount');

        return (float) ($deposits - $withdrawals);
    }

    /**
     * Retorna os meses reais de pagamento de um parcelamento,
     * saltando meses que não têm fatura (billing_skip_months).
     * Parcelas que cairiam em meses pulados são deslocadas para o próximo mês disponível.
     */
    /**
     * Resolve o mês de pagamento, avançando para o próximo mês disponível
     * caso o resultado caia num mês pulado (billing_skip_months).
     */
    private function resolvePaymentMonth(Carbon $chargeDate, CreditCard $card, array $skipMonths): Carbon
    {
        $paymentMonth = $this->resolveBillingMonth($chargeDate, $card);

        while (in_array($paymentMonth->format('Y-m'), $skipMonths)) {
            $paymentMonth->addMonth();
        }

        return $paymentMonth;
    }

    private function getPaymentMonths(Carbon $billingStart, int $installments, array $skipMonths): array
    {
        $months = [];
        $current = $billingStart->copy();
        while (count($months) < $installments) {
            $key = $current->format('Y-m');
            if (! in_array($key, $skipMonths)) {
                $months[] = $key;
            }
            $current->addMonth();
        }

        return $months;
    }

    private function makeExpenseEntry(CreditCardPurchase $purchase, CreditCard $card, int $currentInstallment): array
    {
        return [
            'description' => $purchase->description,
            'card' => $card->name,
            'color' => $card->color,
            'amount' => $purchase->is_recurring
                ? $purchase->total_amount
                : round($purchase->total_amount / $purchase->installments, 2),
            'is_recurring' => $purchase->is_recurring,
            'installments' => $purchase->installments,
            'current_installment' => $currentInstallment,
            // Usado para casar com o lançamento equivalente na API do Pluggy.
            'purchased_at' => Carbon::parse($purchase->purchased_at)->toDateString(),
        ];
    }

    private function resolveBillingMonth(Carbon $purchaseDate, CreditCard $card): Carbon
    {
        // Sem histórico de mudança → fórmula simples
        if ($card->old_closing_day === null || $card->closing_changed_at === null) {
            return $this->computePaymentMonth($purchaseDate, $card->closing_day, $card->due_day);
        }

        $changeDate = Carbon::parse($card->closing_changed_at)->startOfDay();

        // Fechamento que seria aplicado pelas regras antigas
        $oldClosingDate = $purchaseDate->day <= $card->old_closing_day
            ? $purchaseDate->copy()->startOfMonth()->setDay($card->old_closing_day)
            : $purchaseDate->copy()->addMonth()->startOfMonth()->setDay($card->old_closing_day);

        // Se o fechamento antigo é estritamente anterior à mudança → regras antigas ainda valem
        if ($oldClosingDate->lt($changeDate)) {
            return $this->computePaymentMonth($purchaseDate, $card->old_closing_day, $card->old_due_day);
        }

        // Fechamento antigo foi cancelado (está na data de mudança ou depois)
        // Primeiro fechamento real sob as novas regras
        $firstNewClosingDate = $changeDate->copy()->startOfMonth()->setDay($card->closing_day);
        if ($firstNewClosingDate->lt($changeDate)) {
            $firstNewClosingDate->addMonth();
        }

        // Compra acumulou até o primeiro fechamento novo
        if ($purchaseDate->lte($firstNewClosingDate)) {
            $closingCycleMonth = $firstNewClosingDate->copy()->startOfMonth();
            // vencimento no mês seguinte ao fechamento (ex: fecha dia 22, vence dia 1)
            if ($card->due_day !== null && $card->due_day < $card->closing_day) {
                return $closingCycleMonth->addMonth();
            }

            return $closingCycleMonth;
        }

        // Compra após o primeiro fechamento novo → regras novas normalmente
        return $this->computePaymentMonth($purchaseDate, $card->closing_day, $card->due_day);
    }

    /**
     * Retorna o mês de pagamento (vencimento) de uma compra dado o dia de fechamento e vencimento.
     * Se due_day < closing_day → vencimento cai no mês seguinte ao fechamento.
     */
    private function computePaymentMonth(Carbon $purchaseDate, int $closingDay, ?int $dueDay): Carbon
    {
        $closingMonth = $purchaseDate->day <= $closingDay
            ? $purchaseDate->copy()->startOfMonth()
            : $purchaseDate->copy()->addMonth()->startOfMonth();

        if ($dueDay !== null && $dueDay < $closingDay) {
            return $closingMonth->addMonth();
        }

        return $closingMonth;
    }
}
