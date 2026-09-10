<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Saldo real gravado na última importação: é a âncora do acumulado da projeção.
 * O dashboard lê daqui em vez de consultar o Pluggy a cada carregamento.
 */
class BalanceSnapshot extends Model
{
    protected $fillable = [
        'checking',
        'boxes',
        'total',
        'salary_credited',
        'synced_at',
    ];

    protected $casts = [
        'checking' => 'float',
        'boxes' => 'float',
        'total' => 'float',
        'salary_credited' => 'boolean',
        'synced_at' => 'datetime',
    ];

    /** O saldo mais recente, ou null se nunca houve importação. */
    public static function latestSnapshot(): ?self
    {
        return static::orderByDesc('synced_at')->orderByDesc('id')->first();
    }
}
