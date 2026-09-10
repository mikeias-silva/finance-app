<?php

namespace App\Models;

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
}
