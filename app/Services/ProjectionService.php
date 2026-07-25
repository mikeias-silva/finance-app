<?php

namespace App\Services;

use App\Models\CreditCardPurchase;
use App\Models\FixedExpense;
use App\Models\Salary;
use App\Models\VariableExpense;
use App\Models\VariableIncome;
use App\Services\BillingPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ProjectionService
{
    public function project(Carbon $from, Carbon $to): Collection
    {
        $months = collect();
        $current = $from->copy()->startOfMonth();


        while ($current->lte($to)) {
            $period = BillingPeriod::for($current);

            $months->put($current->format('Y-m'), [  // put, não push
                'month'         => $current->copy(),
                'period_start'  => $period['start'],
                'period_end'    => $period['end'],
                'card_expenses' => $this->cardExpensesForMonth($current),
                'fixed_expenses' => $this->fixedExpensesForMonth($current),
                'salary' => $this->salaryForMonth($current),
                'variable_income' => $this->variableIncomesForMonth($current),
                'variable_expenses' => $this->variableExpensesForMonth($current),
                'color' => 'gray' // default, será atualizado depois
            ]);
            $current->addMonth();
        }

        return $months->map(function ($m) {
            $total_expenses = $m['card_expenses']->sum('amount')
                + $m['fixed_expenses']->sum('amount')
                + $m['variable_expenses']->sum('amount');
            $total_incomes  = $m['salary'] + $m['variable_income']->sum('amount');
            $m['balance']   = $total_incomes - $total_expenses;
            $m['rdb_net']   = $this->rdbNetForMonth($m['month']);
            $m['saldo_cc']  = $m['balance'] - $m['rdb_net'];
            return $m;
        });
    }

    public function cardExpensesForMonth(Carbon $month): Collection
    {
        $purchases = CreditCardPurchase::with('creditCard')->get();

        // Modelo por data de cobrança:
        // Para cada parcela N, chargeDate = purchaseDate + N meses.
        // resolveBillingMonth(chargeDate) indica em qual mês de pagamento ela cai.
        // Na fatura "gorda" (ex: agosto cobre jun/2–jul/22), duas cobranças consecutivas
        // podem cair no mesmo mês de pagamento → a parcela aparece duas vezes.
        return $purchases->flatMap(function ($purchase) use ($month) {
            $card         = $purchase->creditCard;
            $purchaseDate = Carbon::parse($purchase->purchased_at);
            $skipMonths   = $card->billing_skip_months ?? [];
            $entries      = [];

            if ($purchase->is_recurring) {
                // Recorrente: itera meses de cobrança até ultrapassar o mês alvo
                for ($k = 0; ; $k++) {
                    $chargeDate   = $purchaseDate->copy()->addMonths($k);
                    $paymentMonth = $this->resolvePaymentMonth($chargeDate, $card, $skipMonths);

                    if ($paymentMonth->gt($month)) break;

                    if ($paymentMonth->format('Y-m') === $month->format('Y-m')) {
                        $entries[] = $this->makeExpenseEntry($purchase, $card, $k + 1);
                    }
                }
            } else {
                // Parcelado: itera cada parcela pela sua data de cobrança real
                for ($n = 0; $n < $purchase->installments; $n++) {
                    $chargeDate   = $purchaseDate->copy()->addMonths($n);
                    $paymentMonth = $this->resolvePaymentMonth($chargeDate, $card, $skipMonths);

                    if ($paymentMonth->format('Y-m') === $month->format('Y-m')) {
                        $entries[] = $this->makeExpenseEntry($purchase, $card, $n + 1);
                    }
                }
            }

            return $entries;
        })->values();
    }

    public function fixedExpensesForMonth(Carbon $month): Collection
    {
        return FixedExpense::where('is_active', true)->get()
            ->filter(function ($e) use ($month) {
                return is_null($e->valid_until) || $month->lte($e->valid_until->endOfMonth());
            })
            ->map(fn($e) => ['description' => $e->description, 'amount' => $e->amount]);
    }

    private function salaryForMonth(Carbon $month): float
    {
        $salary = Salary::where('effective_from', '<=', $month)
            ->orderBy('effective_from')->first();
        return $salary?->amount ?? 0;

    }

    public function variableExpensesForMonth(Carbon $month): Collection
    {
        $period = BillingPeriod::for($month);
        return VariableExpense::query()
            ->whereBetween('paid_at', [
                $period['start']->toDateString(),
                $period['end']->toDateString(),
            ])
            ->get()
            ->map(fn($e) => ['description' => $e->description, 'amount' => $e->amount, 'paid_at' => $e->paid_at]);
    }

    public function variableIncomesForMonth(Carbon $month): Collection
    {
        $period = BillingPeriod::for($month);

        return VariableIncome::query()
            ->whereBetween('received_at', [
                $period['start']->toDateString(),
                $period['end']->toDateString(),
            ])
            ->get()
            ->map(fn($i) => ['description' => $i->description, 'amount' => $i->amount, 'received_at' => $i->received_at]);
    }

    public function rdbNetForMonth(Carbon $month): float
    {
        $period = BillingPeriod::for($month);

        $transactions = \App\Models\InvestmentTransaction::whereBetween('transacted_at', [
            $period['start']->toDateString(),
            $period['end']->toDateString(),
        ])->get();

        $deposits    = $transactions->where('type', 'deposit')->sum('amount');
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
    private function resolvePaymentMonth(Carbon $chargeDate, \App\Models\CreditCard $card, array $skipMonths): Carbon
    {
        $paymentMonth = $this->resolveBillingMonth($chargeDate, $card);

        while (in_array($paymentMonth->format('Y-m'), $skipMonths)) {
            $paymentMonth->addMonth();
        }

        return $paymentMonth;
    }

    private function getPaymentMonths(Carbon $billingStart, int $installments, array $skipMonths): array
    {
        $months  = [];
        $current = $billingStart->copy();
        while (count($months) < $installments) {
            $key = $current->format('Y-m');
            if (!in_array($key, $skipMonths)) {
                $months[] = $key;
            }
            $current->addMonth();
        }
        return $months;
    }

    private function makeExpenseEntry(\App\Models\CreditCardPurchase $purchase, \App\Models\CreditCard $card, int $currentInstallment): array
    {
        return [
            'description'         => $purchase->description,
            'card'                => $card->name,
            'color'               => $card->color,
            'amount'              => $purchase->is_recurring
                ? $purchase->total_amount
                : round($purchase->total_amount / $purchase->installments, 2),
            'is_recurring'        => $purchase->is_recurring,
            'installments'        => $purchase->installments,
            'current_installment' => $currentInstallment,
        ];
    }

    private function resolveBillingMonth(Carbon $purchaseDate, \App\Models\CreditCard $card): Carbon
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
