<?php

namespace App\Services;

use App\Models\CreditCardPurchase;
use App\Models\FixedExpense;
use App\Models\Salary;
use App\Models\VariableIncome;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ProjectionService
{
    public function project(Carbon $from, Carbon $to): Collection
    {
        $months = collect();
        $current = $from->copy()->startOfMonth();

        while ($current->lte($to)) {

            $months->put($current->format('Y-m'), [  // put, não push
                'month' => $current->copy(),
                'card_expenses' => $this->cardExpensesForMonth($current),
                'fixed_expenses' => $this->fixedExpensesForMonth($current),
                'salary' => $this->salaryForMonth($current),
                'variable_income' => $this->variableIncomesForMonth($current),
                'color' => 'gray' // default, será atualizado depois
            ]);
            $current->addMonth();
        }

        return $months->map(function ($m) {
            $total_expenses = $m['card_expenses']->sum('amount')
                + $m['fixed_expenses']->sum('amount');
            $total_incomes = $m['salary'] + $m['variable_income']->sum('amount');
            $m['balance'] = $total_incomes - $total_expenses;
            return $m;
        });
    }

    public function cardExpensesForMonth(Carbon $month): Collection
    {
        //Busca todas as compras onde essa parcela cai nesse mes
        // Compra em jun com 3x -> parcelas jun, jul, ago
        return CreditCardPurchase::with('creditCard')
            ->get()
            ->filter(function ($purchase) use ($month) {
                $purchaseDate = Carbon::parse($purchase->purchased_at);
                $closingDay = $purchase->creditCard->closing_day;

                // Mês de início da 1ª parcela considerando o fechamento
                $billingStart = $this->resolveBillingMonth($purchaseDate, $closingDay);
                if ($purchase->is_recurring) {
                    return $month->gte($billingStart);
                }

                $billingEnd = $billingStart->copy()->addMonths($purchase->installments - 1)->endOfMonth();
                return $month->between($billingStart, $billingEnd);
            })->map(function ($purchase) use ($month) {
                $billingStart       = $this->resolveBillingMonth(
                    Carbon::parse($purchase->purchased_at),
                    $purchase->creditCard->closing_day
                );
                $currentInstallment = $billingStart->diffInMonths($month) + 1;

                return [
                    'description'         => $purchase->description,
                    'card'                => $purchase->creditCard->name,
                    'color'               => $purchase->creditCard->color,
                    'amount'              => $purchase->is_recurring
                        ? $purchase->total_amount
                        : round($purchase->total_amount / $purchase->installments, 2),
                    'is_recurring'        => $purchase->is_recurring,
                    'installments'        => $purchase->installments,
                    'current_installment' => $currentInstallment,
                ];
            })->values();
    }

    public function fixedExpensesForMonth(Carbon $month): Collection
    {
        return FixedExpense::where('is_active', true)->get()
            ->map(fn($e) => ['description' => $e->description, 'amount' => $e->amount]);
    }

    private function salaryForMonth(Carbon $month): float
    {
        $salary = Salary::where('effective_from', '<=', $month)
            ->orderBy('effective_from')->first();
        return $salary?->amount ?? 0;

    }

    public function variableIncomesForMonth(Carbon $month): Collection
    {
        return VariableIncome::whereYear('received_at', $month->year)
            ->whereMonth('received_at', $month->month)
            ->get()
            ->map(fn($i) => ['description' => $i->description, 'amount' => $i->amount]);
    }

    private function resolveBillingMonth(Carbon $purchaseDate, int $closingDay): Carbon
    {
        // Se comprou antes ou no dia de fechamento → entra na fatura deste mês
        // Se comprou depois do fechamento → entra na fatura do mês seguinte
        if ($purchaseDate->day <= $closingDay) {
            return $purchaseDate->copy()->startOfMonth();
        }

        return $purchaseDate->copy()->addMonth()->startOfMonth();
    }
}
