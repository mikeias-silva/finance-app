<?php

namespace Tests\Unit;

use App\Services\BillingPeriod;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * O recorte de todo o app: o ciclo começa no dia em que o salário cai (último dia
 * útil do mês anterior) e termina na véspera do salário seguinte (penúltimo dia
 * útil do mês). Errar aqui desloca receita e despesa de mês inteiro.
 */
class BillingPeriodTest extends TestCase
{
    public function test_the_cycle_runs_from_the_salary_day_to_the_eve_of_the_next_one(): void
    {
        $period = BillingPeriod::for(Carbon::create(2026, 6, 1));

        // 29/mai (sex) é o último dia útil de maio — o salário de junho cai aí.
        $this->assertSame('2026-05-29', $period['start']->toDateString());
        // 30/jun (ter) é o último dia útil de junho e já pertence ao ciclo de julho.
        $this->assertSame('2026-06-29', $period['end']->toDateString());
    }

    public function test_the_weekend_pushes_the_last_business_day_backwards(): void
    {
        // 30/nov/2025 é domingo e 29 é sábado.
        $this->assertSame('2025-11-28', BillingPeriod::lastBusinessDay(Carbon::create(2025, 11, 1))->toDateString());
        $this->assertSame('2025-11-27', BillingPeriod::penultimateBusinessDay(Carbon::create(2025, 11, 1))->toDateString());
    }

    public function test_a_holiday_on_the_last_day_pushes_it_backwards_too(): void
    {
        // 31/mai/2018 foi quinta-feira, mas era Corpus Christi.
        $this->assertSame('2018-05-30', BillingPeriod::lastBusinessDay(Carbon::create(2018, 5, 1))->toDateString());
        $this->assertSame('2018-05-30', BillingPeriod::for(Carbon::create(2018, 6, 1))['start']->toDateString());
    }

    public function test_the_penultimate_business_day_skips_the_weekend(): void
    {
        // Último útil de nov/2026 é segunda 30; o anterior é sexta 27, não domingo 29.
        $this->assertSame('2026-11-30', BillingPeriod::lastBusinessDay(Carbon::create(2026, 11, 1))->toDateString());
        $this->assertSame('2026-11-27', BillingPeriod::penultimateBusinessDay(Carbon::create(2026, 11, 1))->toDateString());
    }

    public function test_weekends_and_holidays_are_not_business_days(): void
    {
        $this->assertFalse(BillingPeriod::isBusinessDay(Carbon::create(2026, 6, 6)));  // sábado
        $this->assertFalse(BillingPeriod::isBusinessDay(Carbon::create(2026, 6, 7)));  // domingo
        $this->assertFalse(BillingPeriod::isBusinessDay(Carbon::create(2026, 6, 4)));  // Corpus Christi
        $this->assertTrue(BillingPeriod::isBusinessDay(Carbon::create(2026, 6, 5)));   // sexta comum
    }

    public function test_moveable_holidays_come_from_easter(): void
    {
        $holidays = BillingPeriod::holidays(2026);

        // Páscoa de 2026: 05/abr.
        $this->assertTrue($holidays->contains('2026-02-16'), 'Carnaval (segunda)');
        $this->assertTrue($holidays->contains('2026-02-17'), 'Carnaval (terça)');
        $this->assertTrue($holidays->contains('2026-04-03'), 'Sexta-Feira Santa');
        $this->assertTrue($holidays->contains('2026-06-04'), 'Corpus Christi');
    }

    public function test_fixed_holidays_include_black_consciousness_day(): void
    {
        $holidays = BillingPeriod::holidays(2026);

        $this->assertTrue($holidays->contains('2026-11-20'), 'Consciência Negra é feriado nacional desde 2024');
        $this->assertTrue($holidays->contains('2026-12-25'));
        $this->assertTrue($holidays->contains('2026-01-01'));
    }

    public function test_the_current_cycle_is_the_one_that_contains_the_day(): void
    {
        // 15/06 está dentro do ciclo de junho (29/05 a 29/06).
        $period = BillingPeriod::current(Carbon::create(2026, 6, 15));

        $this->assertSame('2026-05-29', $period['start']->toDateString());
        $this->assertSame('2026-06-29', $period['end']->toDateString());
    }

    public function test_past_the_end_of_the_cycle_the_current_one_is_already_the_next(): void
    {
        // 31/08 é o último dia útil de agosto: o ciclo de agosto acabou em 28/08
        // e o de setembro começa hoje, com a queda do salário.
        $period = BillingPeriod::current(Carbon::create(2026, 8, 31));

        $this->assertSame('2026-08-31', $period['start']->toDateString());
        $this->assertSame('2026-09-29', $period['end']->toDateString());
    }

    public function test_a_day_between_two_cycles_belongs_to_the_one_about_to_start(): void
    {
        // 30/08 é domingo: o ciclo de agosto fechou em 28 e o de setembro só abre
        // em 31. O dia não cai em ciclo nenhum — vale o que está por começar.
        $period = BillingPeriod::current(Carbon::create(2026, 8, 30));

        $this->assertSame('2026-08-31', $period['start']->toDateString());
    }

    public function test_the_period_does_not_mutate_the_month_it_receives(): void
    {
        $month = Carbon::create(2026, 6, 15);

        BillingPeriod::for($month);

        $this->assertSame('2026-06-15', $month->toDateString());
    }
}
