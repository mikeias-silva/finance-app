<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditCardPurchase extends Model
{
    protected $table = 'credit_card_purchases';

    protected $fillable = ['credit_card_id', 'description', 'total_amount', 'installments', 'purchased_at', 'excluded_from_projection'];

    protected $casts = ['excluded_from_projection' => 'boolean'];

    /**
     * Prefixo da diferença mensal de uma assinatura de valor variável. Fica no
     * cartão para cair na fatura em que a cobrança real entrou.
     */
    public const ADJUSTMENT_PREFIX = 'Ajuste ';

    public static function isAdjustment(string $description): bool
    {
        return str_starts_with($description, self::ADJUSTMENT_PREFIX);
    }

    public function creditCard()
    {
        return $this->belongsTo(CreditCard::class, 'credit_card_id');
    }
}
