<?php

namespace Tests\Feature;

use App\Models\Salary;
use App\Models\VariableExpense;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * O Dashboard 3 mostra os mesmos números do 2. Todos os ciclos vão na página para
 * horizonte e seleção responderem sem recarregar.
 */
class Dashboard3Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
        Carbon::setTestNow('2026-11-06');
        Salary::create(['amount' => 5000.00, 'effective_from' => '2025-01-01']);
    }

    public function test_it_opens_on_six_cycles_with_the_whole_horizon_on_the_page(): void
    {
        $response = $this->unlocked()->get('/dashboard3');

        $response->assertOk();
        $this->assertSame(6, $response->viewData('months'));
        $this->assertCount(24, $response->viewData('payload')['cycles']);
    }

    public function test_the_horizon_stays_within_the_slider_range(): void
    {
        $this->assertSame(3, $this->unlocked()->get('/dashboard3?months=1')->viewData('months'));
        $this->assertSame(24, $this->unlocked()->get('/dashboard3?months=99')->viewData('months'));
    }

    public function test_it_shows_the_same_numbers_as_dashboard_2(): void
    {
        VariableExpense::create(['description' => 'Reforma', 'amount' => 900.00, 'paid_at' => '2026-12-10']);

        $second = $this->unlocked()->get('/dashboard2?months=24')->viewData('cycles')->values();
        $third = $this->unlocked()->get('/dashboard3')->viewData('payload')['cycles'];

        foreach ($second as $index => $cycle) {
            $this->assertEquals(round($cycle['accumulated'], 2), $third[$index]['accumulated']);
            $this->assertEquals(round($cycle['income'], 2), $third[$index]['income']);
            $this->assertEquals(round($cycle['expenses'], 2), $third[$index]['expenses']);
        }
    }

    public function test_a_statement_description_cannot_close_the_data_script(): void
    {
        VariableExpense::create(['description' => '</script><script>alert(1)</script>', 'amount' => 10.00, 'paid_at' => '2026-11-10']);

        $response = $this->unlocked()->get('/dashboard3');

        $response->assertDontSee('</script><script>alert(1)', false);
    }

    public function test_the_page_never_calls_pluggy(): void
    {
        $this->unlocked()->get('/dashboard3')->assertOk();

        Http::assertNothingSent();
    }

    public function test_it_is_linked_in_the_sidebar(): void
    {
        $this->unlocked()->get('/dashboard3')->assertSee(route('dashboard3'));
    }

    public function test_it_requires_the_app_password(): void
    {
        $this->get('/dashboard3')->assertRedirect('/login');
    }
}
