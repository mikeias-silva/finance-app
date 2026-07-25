<?php

namespace App\Services;

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
            $days = $transaction->transacted_at->diffInDays($today);

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

    public function availableCapacity(InvestmentBox $box): ?float
    {
        if (is_null($box->max_amount)) {
            return null;
        }

        return max(0.0, $box->max_amount - $this->currentBalance($box));
    }

    private function getCdiRateAt(Carbon $date): float
    {
        $rate = CdiRate::where('effective_from', '<=', $date)
            ->orderByDesc('effective_from')
            ->first();

        return $rate?->rate ?? 13.25;
    }
}
