<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditCard extends Model
{
    protected $table = 'credit_cards';
    protected $fillable = ['name', 'color', 'is_active'];
}
