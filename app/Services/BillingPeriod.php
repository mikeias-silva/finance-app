<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Calcula o período de ciclo salarial para um dado mês,
 * respeitando fins de semana E feriados nacionais brasileiros.
 *
 * Regra:
 *  - Início: último dia útil do mês anterior  (data em que o salário cai)
 *  - Fim:    penúltimo dia útil do mês atual  (dia antes do próximo salário)
 *
 * Exemplo para Junho/2026:
 *  - Início: 29/mai (sex) — último dia útil de maio
 *  - Fim:    29/jun (seg) — penúltimo dia útil de junho
 *            (30/jun = último dia útil = queda do salário de julho)
 */
class BillingPeriod
{
    /** Retorna [start => Carbon, end => Carbon] para o mês informado. */
    public static function for(Carbon $month): array
    {
        return [
            'start' => self::lastBusinessDay($month->copy()->subMonth()),
            'end' => self::penultimateBusinessDay($month->copy()),
        ];
    }

    /**
     * O ciclo em que um dia está — por padrão, hoje.
     *
     * Não basta olhar o mês do calendário: passado o penúltimo dia útil, o mês já
     * corre no ciclo seguinte. E entre o fim de um ciclo e o começo do outro sobram
     * dias soltos (o fim de semana antes do último dia útil); esses valem como o
     * ciclo prestes a começar.
     */
    public static function current(?Carbon $day = null): array
    {
        $day = ($day ?? Carbon::today())->copy()->startOfDay();
        $period = self::for($day->copy()->startOfMonth());

        return $day->lte($period['end'])
            ? $period
            : self::for($day->copy()->startOfMonth()->addMonthNoOverflow());
    }

    /** Último dia útil (seg–sex, excluindo feriados nacionais) de um mês. */
    public static function lastBusinessDay(Carbon $month): Carbon
    {
        $day = $month->copy()->endOfMonth()->startOfDay();
        $holidays = self::holidays($month->year);

        while (!self::isBusinessDay($day, $holidays)) {
            $day->subDay();
        }

        return $day;
    }

    /** Penúltimo dia útil de um mês (o dia útil anterior ao último). */
    public static function penultimateBusinessDay(Carbon $month): Carbon
    {
        $holidays = self::holidays($month->year);
        $day = self::lastBusinessDay($month)->subDay();

        while (!self::isBusinessDay($day, $holidays)) {
            $day->subDay();
        }

        return $day;
    }

    /** Verifica se uma data é dia útil (não é fim de semana nem feriado nacional). */
    public static function isBusinessDay(Carbon $day, ?Collection $holidays = null): bool
    {
        if ($day->isWeekend()) {
            return false;
        }

        $holidays ??= self::holidays($day->year);

        return !$holidays->contains($day->toDateString());
    }

    /**
     * Retorna todos os feriados nacionais brasileiros do ano informado.
     * Inclui feriados fixos e móveis (baseados na Páscoa).
     */
    public static function holidays(int $year): Collection
    {
        $easter = self::easter($year);

        $dates = [
            // Feriados fixos
            "{$year}-01-01", // Ano Novo
            "{$year}-04-21", // Tiradentes
            "{$year}-05-01", // Dia do Trabalho
            "{$year}-09-07", // Independência do Brasil
            "{$year}-10-12", // Nossa Senhora Aparecida
            "{$year}-11-02", // Finados
            "{$year}-11-15", // Proclamação da República
            "{$year}-11-20", // Consciência Negra (lei federal desde 2024)
            "{$year}-12-25", // Natal

            // Feriados móveis (baseados na Páscoa)
            $easter->copy()->subDays(48)->toDateString(), // Carnaval (segunda)
            $easter->copy()->subDays(47)->toDateString(), // Carnaval (terça)
            $easter->copy()->subDays(2)->toDateString(),  // Sexta-Feira Santa
            $easter->toDateString(),                       // Páscoa (domingo — não é dia útil, mas incluído)
            $easter->copy()->addDays(60)->toDateString(),  // Corpus Christi
        ];

        return collect($dates);
    }

    /**
     * Calcula a data da Páscoa pelo algoritmo Gregoriano Anônimo.
     */
    private static function easter(int $year): Carbon
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);

        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return Carbon::createFromDate($year, $month, $day)->startOfDay();
    }
}
