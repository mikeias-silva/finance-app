<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FixedExpense extends Model
{
    protected $table = 'fixed_expenses';

    protected $fillable = ['description', 'category', 'amount', 'is_active', 'valid_until'];

    protected $casts = [
        'valid_until' => 'date',
    ];
}
