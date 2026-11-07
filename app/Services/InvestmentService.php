<?php

namespace App\Services;

use App\Models\BalanceSnapshot;
use App\Models\CdiRate;
use App\Models\InvestmentBox;
use Carbon\Carbon;

class InvestmentService
{
    public function currentBalance(InvestmentBox $box): float
    {
        return $box->transactions->reduce(function ($carry, $transaction) {
            return $carry + ($transaction->type === 'deposit' ? $transaction->amount : -$transaction->amount);
        }, 0.0);
    }

    public function calculateYield(InvestmentBox $box): float
    {
        $today = Carbon::today();
        $yield = 0.0;

        foreach ($box->transactions as $transaction) {
            $cdiRate = $this->getCdiRateAt($transaction->transacted_at);
            $adjRate = $cdiRate * ($box->rate_percentage / 100) / 100;
            $dailyRate = pow(1 + $adjRate, 1 / 252) - 1;
            $days = $this->businessDaysBetween($transaction->transacted_at, $today);

            $transactionYield = $transaction->amount * (pow(1 + $dailyRate, $days) - 1);

            if ($transaction->type === 'withdrawal') {
                $transactionYield *= -1;
            }

            $yield += $transactionYield;
        }

        return max(0.0, $yield);
    }

    public function totalWithYield(InvestmentBox $box): float
    {
        return $this->currentBalance($box) + $this->calculateYield($box);
    }

    /**
     * O dinheiro de cada caixinha ativa, para projetar o rendimento. A base é o
     * saldo real lido na importação, que é o que está na âncora; como ele vem como
     * um total só, cada caixinha fica com a sua parte no que foi depositado. Antes
     * da primeira importação, vale o total simulado, a mesma âncora da projeção.
     *
     * @return array<int, array{rate: float, balance: float}>
     */
    public function yieldingBoxes(): array
    {
        $boxes = InvestmentBox::with('transactions')->where('is_active', true)->get();
        $snapshot = BalanceSnapshot::latestSnapshot();
        $deposited = $boxes->sum(fn (InvestmentBox $box) => $this->currentBalance($box));

        return $boxes
            ->map(fn (InvestmentBox $box) => [
                'rate' => (float) $box->rate_percentage,
                'balance' => $snapshot && $deposited > 0
                    ? $snapshot->boxes * $this->currentBalance($box) / $deposited
                    : $this->totalWithYield($box),
            ])
            ->filter(fn (array $box) => $box['balance'] > 0)
            ->values()
            ->all();
    }

    /**
     * Quanto as caixinhas rendem no período, com o CDI vigente hoje. O saldo de cada
     * uma cresce junto, porque o rendimento de um ciclo também rende no seguinte.
     * O valor é bruto, antes do imposto, como o rendimento já mostrado na tela.
     *
     * @param  array<int, array{rate: float, balance: float}>  $boxes
     */
    public function accrue(array &$boxes, Carbon $from, Carbon $until): float
    {
        $days = $this->businessDaysBetween($from, $until);
        $cdi = $this->getCdiRateAt(Carbon::today());
        $total = 0.0;

        foreach ($boxes as &$box) {
            $earned = $box['balance'] * (pow(1 + $cdi * ($box['rate'] / 100) / 100, $days / 252) - 1);
            $box['balance'] += $earned;
            $total += $earned;
        }

        return $total;
    }

    public function availableCapacity(InvestmentBox $box): ?float
    {
        if (is_null($box->max_amount)) {
            return null;
        }

        return max(0.0, $box->max_amount - $this->currentBalance($box));
    }

    /**
     * Dias úteis entre duas datas. A taxa diária é derivada da convenção de 252
     * dias úteis por ano, então contar dias corridos superestima o rendimento
     * em cerca de 45% (365/252).
     */
    private function businessDaysBetween(Carbon $from, Carbon $to): int
    {
        $days = 0;
        $cursor = $from->copy()->startOfDay();
        $holidays = [];

        while ($cursor->lt($to)) {
            $holidays[$cursor->year] ??= BillingPeriod::holidays($cursor->year);

            if (BillingPeriod::isBusinessDay($cursor, $holidays[$cursor->year])) {
                $days++;
            }

            $cursor->addDay();
        }

        return $days;
    }

    private function getCdiRateAt(Carbon $date): float
    {
        $rate = CdiRate::where('effective_from', '<=', $date)
            ->orderByDesc('effective_from')
            ->first();

        return $rate?->rate ?? 13.25;
    }
}
