<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatementPayment extends Model
{
    protected $fillable = [
        'label',
        'fixed_expense_id',
        'credit_card_id',
        'amount',
        'paid_at',
        'identifier',
    ];

    protected $casts = [
        'paid_at' => 'date',
        'amount' => 'float',
    ];

    public function fixedExpense(): BelongsTo
    {
        return $this->belongsTo(FixedExpense::class);
    }

    public function creditCard(): BelongsTo
    {
        return $this->belongsTo(CreditCard::class);
    }
}
