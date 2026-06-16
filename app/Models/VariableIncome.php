<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VariableIncome extends Model
{
    protected $table = 'variable_incomes';
    protected $fillable = ['description',
    'amount',
    'received_at'];
}
