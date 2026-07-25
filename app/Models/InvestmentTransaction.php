<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestmentTransaction extends Model
{
    protected $fillable = ['investment_box_id', 'type', 'amount', 'transacted_at', 'notes', 'identifier'];

    protected $casts = [
        'transacted_at' => 'date',
        'amount' => 'float',
    ];

    public function box(): BelongsTo
    {
        return $this->belongsTo(InvestmentBox::class, 'investment_box_id');
    }
}
