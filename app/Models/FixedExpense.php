<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FixedExpense extends Model
{
    protected $table = 'fixed_expenses';
    protected $fillable = ['name', 'amount', 'due_date'];
}
