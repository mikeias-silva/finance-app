<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvestmentBox extends Model
{
    protected $fillable = ['name', 'color', 'rate_percentage', 'max_amount', 'is_active'];

    protected $casts = [
        'rate_percentage' => 'float',
        'max_amount' => 'float',
        'is_active' => 'boolean',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(InvestmentTransaction::class)->orderBy('transacted_at');
    }
}
