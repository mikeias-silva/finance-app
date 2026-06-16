<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Salary extends Model
{
    protected $table = 'salaries';
    protected $fillable = ['amount', 'effective_from'];
}
