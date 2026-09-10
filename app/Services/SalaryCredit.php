<?php

namespace App\Services;

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
