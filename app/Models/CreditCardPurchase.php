<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditCardPurchase extends Model
{
    protected $table = 'credit_card_purchases';
    protected $fillable = ['credit_card_id', 'description', 'total_amount', 'installments', 'purchased_at'];

    public function creditCard()
    {
        return $this->belongsTo(CreditCard::class, 'credit_card_id');
    }
}
