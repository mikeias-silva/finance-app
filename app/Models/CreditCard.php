<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditCard extends Model
{
    protected $table = 'credit_cards';
    protected $fillable = ['name', 'color', 'is_active', 'closing_day', 'due_day', 'old_closing_day', 'old_due_day', 'closing_changed_at', 'billing_skip_months'];

    protected $casts = [
        'closing_changed_at'  => 'date',
        'billing_skip_months' => 'array',
    ];
}
