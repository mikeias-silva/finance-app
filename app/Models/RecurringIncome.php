<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RecurringIncome extends Model
{
    protected $table = 'recurring_incomes';

    protected $fillable = ['description', 'amount', 'is_active', 'effective_from', 'valid_until'];

    protected $casts = [
        'amount' => 'float',
        'is_active' => 'boolean',
        'effective_from' => 'date',
        'valid_until' => 'date',
    ];

    /**
     * Receitas pagas no dia de pagamento informado. whereDate porque o cast `date`
     * grava "Y-m-d 00:00:00": na estreia, comparar a coluna crua com a data pura
     * deixaria o registro de fora.
     */
    public function scopePaidOn(Builder $query, Carbon $paymentDay): void
    {
        $date = $paymentDay->toDateString();

        $query->where('is_active', true)
            ->whereDate('effective_from', '<=', $date)
            ->where(fn (Builder $q) => $q->whereNull('valid_until')->orWhereDate('valid_until', '>=', $date));
    }
}
