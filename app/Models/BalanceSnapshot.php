<?php

namespace App\Models;

use App\Services\BillingPeriod;
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

    /**
     * O salário do ciclo de hoje já está neste saldo? A resposta gravada fala do
     * ciclo em que a leitura aconteceu. Lida antes de o ciclo de hoje começar, a
     * leitura não tem como conter o salário dele — que cai no primeiro dia —, seja
     * qual for a resposta: sem isto, o salário sairia do previsto sem estar no
     * saldo e a projeção perderia um salário inteiro até a próxima importação.
     *
     * Dentro do ciclo, vale o que foi gravado; null segue deixando o calendário decidir.
     */
    public function salaryCreditedInCurrentCycle(): ?bool
    {
        if ($this->synced_at->lt(BillingPeriod::current()['start'])) {
            return false;
        }

        return $this->salary_credited;
    }
}
