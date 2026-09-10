<?php

namespace Tests\Feature;

use App\Models\RecurringIncome;
use App\Models\Salary;
use App\Services\CaixaBillService;
use App\Services\ProjectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Receita que se repete todo ciclo e cai no mesmo crédito do salário. O caso que
 * originou isto: uma gratificação de R$ 1.342,05 estreando no pagamento de
 * 30/09/2026 — último dia útil de setembro, que abre o ciclo de outubro.
 */
class RecurringIncomeTest extends TestCase
{
    use RefreshDatabase;

    private ProjectionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ProjectionService;
    }

    public function test_it_starts_on_the_cycle_whose_payment_day_it_names(): void
    {
        $this->gratification();

        $projection = $this->project('2026-09', '2026-10');

        // O ciclo de setembro é pago em 31/08: a gratificação ainda não existia.
        $this->assertCount(0, $projection['2026-09']['recurring_income']);
        $this->assertSame(
            ['Gratificação'],
            $projection['2026-10']['recurring_income']->pluck('description')->all()
        );
    }

    public function test_it_is_dated_on_the_payment_day_of_each_cycle(): void
    {
        $this->gratification();

        $october = $this->project('2026-10')['2026-10']['recurring_income']->first();

        $this->assertSame('2026-09-30', $october['received_at']);
        $this->assertSame('2026-09-30', $this->project('2026-10')['2026-10']['period_start']->toDateString());
    }

    public function test_it_repeats_on_every_following_cycle(): void
    {
        $this->gratification();

        $projection = $this->project('2026-10', '2027-01');

        foreach (['2026-10', '2026-11', '2026-12', '2027-01'] as $month) {
            $this->assertCount(1, $projection[$month]['recurring_income'], $month);
        }
    }

    public function test_it_adds_to_the_month_balance(): void
    {
        Salary::create(['amount' => 5000.00, 'effective_from' => '2025-01-01']);
        $this->gratification();

        $projection = $this->project('2026-09', '2026-10');

        $this->assertEquals(5000.00, $projection['2026-09']['balance']);
        $this->assertEquals(6342.05, $projection['2026-10']['balance']);
    }

    public function test_an_inactive_income_is_ignored(): void
    {
        $this->gratification(['is_active' => false]);

        $this->assertCount(0, $this->project('2026-10')['2026-10']['recurring_income']);
    }

    public function test_the_validity_end_stops_it_after_the_last_payment(): void
    {
        $this->gratification(['valid_until' => '2026-10-30']);

        $projection = $this->project('2026-10', '2026-12');

        // Pagamentos em 30/09 e 30/10 entram; o de 30/11 já passou do prazo.
        $this->assertCount(1, $projection['2026-10']['recurring_income']);
        $this->assertCount(1, $projection['2026-11']['recurring_income']);
        $this->assertCount(0, $projection['2026-12']['recurring_income']);
    }

    public function test_on_the_credit_day_it_is_still_pending(): void
    {
        Carbon::setTestNow('2026-09-30');
        Salary::create(['amount' => 5000.00, 'effective_from' => '2025-01-01']);
        $this->gratification();

        // A âncora vem da sincronia diária do Pluggy: no dia do crédito o saldo
        // lido ainda é o de antes, então salário e gratificação seguem previstos.
        $october = $this->service->accumulate($this->project('2026-10'), 0.0)['2026-10'];

        $this->assertEquals(6342.05, $october['pending']);
    }

    public function test_it_leaves_the_pending_together_with_the_salary(): void
    {
        Carbon::setTestNow('2026-10-05');
        Salary::create(['amount' => 5000.00, 'effective_from' => '2025-01-01']);
        $this->gratification();

        $october = $this->service->accumulate($this->project('2026-10'), 0.0)['2026-10'];

        $this->assertEquals(0.0, $october['pending']);
    }

    public function test_the_statement_answer_wins_over_the_calendar(): void
    {
        Carbon::setTestNow('2026-09-30');
        Salary::create(['amount' => 5000.00, 'effective_from' => '2025-01-01']);
        $this->gratification();

        // Extrato já mostra o crédito no mesmo dia: sai da pendência junto.
        $october = $this->service->accumulate($this->project('2026-10'), 0.0, salaryCredited: true)['2026-10'];

        $this->assertEquals(0.0, $october['pending']);
    }

    public function test_it_can_be_registered_through_the_screen(): void
    {
        $this->unlocked()->post('/recurring-incomes', [
            'description' => 'Gratificação',
            'amount' => '1342.05',
            'effective_from' => '2026-09-30',
            'is_active' => '1',
        ])->assertRedirect(route('recurring-incomes.index'));

        $this->assertDatabaseHas('recurring_incomes', [
            'description' => 'Gratificação',
            'amount' => 1342.05,
            'is_active' => true,
        ]);
    }

    public function test_the_end_of_validity_cannot_precede_the_start(): void
    {
        $this->unlocked()->post('/recurring-incomes', [
            'description' => 'Gratificação',
            'amount' => '1342.05',
            'effective_from' => '2026-09-30',
            'valid_until' => '2026-08-30',
        ])->assertSessionHasErrors('valid_until');
    }

    public function test_the_listing_shows_the_active_total(): void
    {
        $this->gratification();
        $this->gratification(['description' => 'Ajuda de custo', 'amount' => 300.00, 'is_active' => false]);

        $this->unlocked()->get('/recurring-incomes')
            ->assertOk()
            ->assertSee('Gratificação')
            ->assertSee('1.342,05');
    }

    private function gratification(array $attrs = []): RecurringIncome
    {
        return RecurringIncome::create(array_merge([
            'description' => 'Gratificação',
            'amount' => 1342.05,
            'is_active' => true,
            'effective_from' => '2026-09-30',
            'valid_until' => null,
        ], $attrs));
    }

    private function project(string $from, ?string $to = null): Collection
    {
        return $this->service->project(
            Carbon::parse($from.'-01'),
            Carbon::parse(($to ?? $from).'-01'),
        );
    }
}
