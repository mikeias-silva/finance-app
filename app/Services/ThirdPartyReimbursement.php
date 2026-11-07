<?php

namespace App\Services;

use App\Models\CreditCardPurchase;
use Carbon\Carbon;

/**
 * Identifica no extrato o dinheiro devolvido por quem pagou uma compra feita no
 * cartão do titular. A compra está fora dos cálculos, então a devolução também
 * precisa ficar: contá-la como receita somaria um dinheiro que só repõe a fatura.
 *
 * Quem devolve manda o valor da parcela. O nome não ajuda, porque o extrato às
 * vezes omite o pagador, e por isso é o valor exato que identifica o crédito.
 */
class ThirdPartyReimbursement
{
    /** Folga depois da última parcela para uma devolução feita com atraso. */
    private const GRACE_MONTHS = 3;

    public function matches(array $transaction): bool
    {
        $amount = round((float) ($transaction['amount'] ?? 0), 2);

        if ($amount <= 0) {
            return false;
        }

        $date = Carbon::parse($transaction['date'])->startOfDay();

        return CreditCardPurchase::where('excluded_from_projection', true)->get()->contains(
            fn (CreditCardPurchase $purchase) => $this->isInstallmentOf($purchase, $amount, $date)
        );
    }

    private function isInstallmentOf(CreditCardPurchase $purchase, float $amount, Carbon $date): bool
    {
        $installments = max(1, (int) $purchase->installments);
        $start = Carbon::parse($purchase->purchased_at)->startOfDay();
        $end = $start->copy()->addMonths($installments + self::GRACE_MONTHS);

        return round((float) $purchase->total_amount / $installments, 2) === $amount
            && $date->betweenIncluded($start, $end);
    }
}
