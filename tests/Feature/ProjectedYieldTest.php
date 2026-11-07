<?php

namespace Tests\Feature;

use App\Models\BalanceSnapshot;
use App\Models\InvestmentBox;
use App\Models\InvestmentTransaction;
use App\Models\Salary;
use App\Services\ProjectionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O dinheiro nas caixinhas continua rendendo nos meses projetados. A base é o saldo
 * real lido na importação, que já é parte da âncora; o rendimento só acrescenta.
 */
class ProjectedYieldTest extends TestCase
{
    use RefreshDatabase;

    private const CDI = 13.25; // taxa padrão quando não há CDI cadastrado

    private ProjectionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Segunda-feira, primeiro dia do ciclo de novembro seria 30/10: hoje está no meio dele.
        Carbon::setTestNow('2026-11-09');
        Salary::create(['amount' => 5000.00, 'effective_from' => '2025-01-01']);
        $this->service = new ProjectionService;
    }

    public function test_a_future_cycle_earns_on_every_business_day(): void
    {
        $this->box(100, 1000.00);
        $this->snapshot(boxes: 1000.00);

        // Dezembro vai de 30/11 a 30/12: 22 dias úteis (25/12 é feriado).
        $december = $this->chain()['2026-12'];
        $november = $this->chain()['2026-11'];

        $this->assertEqualsWithDelta(
            (1000 + $november['yield']) * ($this->growth(100, 22) - 1),
            $december['yield'],
            0.01
        );
    }

    public function test_the_current_cycle_only_earns_from_today_on(): void
    {
        $this->box(100, 1000.00);
        $this->snapshot(boxes: 1000.00);

        // De 09/11 a 27/11, com 20/11 feriado (Consciência Negra): 14 dias úteis.
        $this->assertEqualsWithDelta(1000 * ($this->growth(100, 14) - 1), $this->chain()['2026-11']['yield'], 0.01);
    }

    public function test_the_yield_enters_the_balance_but_not_the_cycle_result(): void
    {
        $this->box(100, 1000.00);
        $this->snapshot(boxes: 1000.00, total: 1000.00);

        $december = $this->chain()['2026-12'];
        $november = $this->chain()['2026-11'];

        $this->assertEqualsWithDelta(
            $november['accumulated'] + $december['balance'] + $december['yield'],
            $december['accumulated'],
            0.001
        );
        $this->assertEquals(5000.00, $december['balance']);
    }

    public function test_each_box_earns_at_its_own_rate_over_its_share_of_the_real_balance(): void
    {
        $this->box(115, 900.00);
        $this->box(100, 100.00);
        // O saldo real é maior que o depositado: já traz o que as caixinhas renderam.
        $this->snapshot(boxes: 2000.00);

        $expected = 1800 * ($this->growth(115, 14) - 1) + 200 * ($this->growth(100, 14) - 1);

        $this->assertEqualsWithDelta($expected, $this->chain()['2026-11']['yield'], 0.01);
    }

    public function test_an_inactive_box_does_not_earn(): void
    {
        $this->box(100, 1000.00, active: false);
        $this->snapshot(boxes: 1000.00);

        $this->assertEquals(0.0, $this->chain()['2026-11']['yield']);
    }

    public function test_without_boxes_nothing_changes(): void
    {
        $this->assertEquals(0.0, $this->chain()['2026-12']['yield']);
    }

    private function chain()
    {
        return $this->service->accumulate(
            $this->service->project(now()->setDate(2026, 11, 1), now()->setDate(2026, 12, 1)),
            0.0
        );
    }

    private function growth(int $percentOfCdi, int $days): float
    {
        return (1 + self::CDI * ($percentOfCdi / 100) / 100) ** ($days / 252);
    }

    private function box(int $rate, float $deposit, bool $active = true): void
    {
        $box = InvestmentBox::create(['name' => "Caixinha {$rate}", 'rate_percentage' => $rate, 'is_active' => $active]);
        InvestmentTransaction::create([
            'investment_box_id' => $box->id,
            'type' => 'deposit',
            'amount' => $deposit,
            'transacted_at' => '2026-11-09',
        ]);
    }

    private function snapshot(float $boxes, float $total = 0.0): void
    {
        BalanceSnapshot::create([
            'checking' => 0,
            'boxes' => $boxes,
            'total' => $total,
            'synced_at' => now(),
        ]);
    }
}
