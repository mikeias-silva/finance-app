<?php

namespace App\Services;

use App\Models\RecurringIncome;
use App\Models\Salary;
use Carbon\Carbon;

/**
 * Identifica o crédito do salário no extrato da conta.
 *
 * Vive fora do importador porque quem pergunta são dois: a importação, que precisa
 * não lançar o salário como receita variável (a projeção já o conta por conta
 * própria), e a projeção, que precisa saber se ele já caiu para não somá-lo de novo
 * por cima do saldo real.
 *
 * O salário chega como transferência do próprio titular vinda de outro banco, no
 * primeiro dia do ciclo, com valor próximo ao cadastrado.
 */
class SalaryCredit
{
    /** Quanto o crédito pode divergir do salário cadastrado e ainda ser ele. */
    private const TOLERANCE = 0.2;

    public function __construct(private readonly StatementClassifier $classifier) {}

    /** Algum destes lançamentos é o crédito do salário? */
    public function foundIn(array $transactions): bool
    {
        foreach ($transactions as $transaction) {
            if ($this->matches($transaction)) {
                return true;
            }
        }

        return false;
    }

    /** Total das receitas recorrentes pagas no ciclo desta data. */
    public function recurringTotal(string $date): float
    {
        return round((float) $this->recurringIncomes($date)->sum('amount'), 2);
    }

    /**
     * Este lançamento é uma receita recorrente cadastrada, paga fora do crédito do
     * salário? O valor exato é o que a identifica: nem pagador nem descrição são
     * conhecidos, e o dia pode não ser o de abertura do ciclo.
     */
    public function isRecurringIncome(array $transaction): bool
    {
        $amount = round((float) ($transaction['amount'] ?? 0), 2);

        if ($amount <= 0) {
            return false;
        }

        $date = Carbon::parse($transaction['date'])->toDateString();

        return $this->recurringIncomes($date)
            ->contains(fn (RecurringIncome $income) => round($income->amount, 2) === $amount);
    }

    private function recurringIncomes(string $date)
    {
        return RecurringIncome::paidOn(BillingPeriod::current(Carbon::parse($date)->startOfDay())['start'])->get();
    }

    /** Este lançamento é o crédito do salário? */
    public function matches(array $transaction): bool
    {
        $amount = (float) ($transaction['amount'] ?? 0);

        if ($amount <= 0) {
            return false;
        }

        $isOwn = $this->classifier->isOwnTransfer($transaction['paymentData'] ?? null)
            || $this->classifier->isSalary($transaction['description'] ?? '');

        if (! $isOwn) {
            return false;
        }

        $day = Carbon::parse($transaction['date'])->startOfDay();
        $date = $day->toDateString();

        // O salário cai no primeiro dia do ciclo. Quem é o ciclo desse dia se
        // pergunta direto, em vez de reconstruí-lo somando um mês à data: quando
        // ela é dia 31, a soma estoura para o mês seguinte e desloca o ciclo todo.
        if ($date !== BillingPeriod::current($day)['start']->toDateString()) {
            return false;
        }

        $expected = (float) (Salary::where('effective_from', '<=', $date)
            ->orderByDesc('effective_from')
            ->first()?->amount ?? 0);

        return $expected > 0 && abs($amount - $expected) / $expected <= self::TOLERANCE;
    }
}
