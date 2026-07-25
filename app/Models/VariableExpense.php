<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VariableExpense extends Model
{
    protected $table = 'variable_expenses';

    protected $fillable = [
        'identifier',
        'description',
        'category',
        'amount',
        'paid_at',
    ];
}
