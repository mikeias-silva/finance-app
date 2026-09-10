<?php

namespace Tests\Feature;

use App\Models\CdiRate;
use App\Models\InvestmentBox;
use App\Models\InvestmentTransaction;
use App\Services\InvestmentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * As caixinhas rendem a um percentual do CDI, capitalizado em dias úteis. Os
 * valores esperados abaixo saem da fórmula do próprio service — estão fixados de
 * propósito, para que uma mudança na convenção apareça como falha e não como um
 * número um pouco diferente na tela.
 */
class InvestmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private InvestmentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new InvestmentService;

        // 20 dias úteis depois de 05/01/2026, sem nenhum feriado no intervalo.
        Carbon::setTestNow('2026-02-02');
    }

    public function test_the_balance_is_deposits_minus_withdrawals(): void
    {
        $box = $this->box();
        $this->deposit($box, 1000.00, '2026-01-05');
        $this->deposit($box, 500.00, '2026-01-10');
        $this->withdraw($box, 200.00, '2026-01-15');

        $this->assertSame(1300.00, $this->service->currentBalance($box->fresh()));
    }

    public function test_a_box_without_a_ceiling_has_no_capacity_limit(): void
    {
        $box = $this->box(['max_amount' => null]);

        $this->assertNull($this->service->availableCapacity($box));
    }

    public function test_the_capacity_never_goes_negative(): void
    {
        $box = $this->box(['max_amount' => 1000.00]);
        $this->deposit($box, 1500.00, '2026-01-05');

        $this->assertSame(0.0, $this->service->availableCapacity($box->fresh()));
    }

    public function test_the_capacity_is_what_still_fits_under_the_ceiling(): void
    {
        $box = $this->box(['max_amount' => 1000.00]);
        $this->deposit($box, 400.00, '2026-01-05');

        $this->assertSame(600.00, $this->service->availableCapacity($box->fresh()));
    }

    public function test_the_yield_is_capitalised_over_business_days_not_calendar_days(): void
    {
        $box = $this->box();
        $this->deposit($box, 10000.00, '2026-01-05');

        $yield = $this->service->calculateYield($box->fresh());

        // 20 dias úteis a 13,25% (CDI padrão) sobre 10.000.
        $this->assertEqualsWithDelta(99.2412536769, $yield, 0.0000001);
        // Os mesmos 28 dias corridos dariam 139,21 — 40% a mais.
        $this->assertLessThan(139.0, $yield);
    }

    public function test_without_a_registered_rate_it_falls_back_to_the_default_cdi(): void
    {
        $box = $this->box();
        $this->deposit($box, 10000.00, '2026-01-05');

        $this->assertSame(0, CdiRate::count());
        $this->assertEqualsWithDelta(99.2412536769, $this->service->calculateYield($box->fresh()), 0.0000001);
    }

    public function test_it_uses_the_rate_in_force_on_the_transaction_date(): void
    {
        CdiRate::create(['rate' => 8.00, 'effective_from' => '2025-01-01']);
        // Taxa nova, mas posterior ao depósito: não vale para ele.
        CdiRate::create(['rate' => 20.00, 'effective_from' => '2026-01-10']);

        $box = $this->box();
        $this->deposit($box, 10000.00, '2026-01-05');

        $this->assertEqualsWithDelta(61.2671112431, $this->service->calculateYield($box->fresh()), 0.0000001);
    }

    public function test_the_box_percentage_scales_the_rate(): void
    {
        $box = $this->box(['rate_percentage' => 50.00]);
        $this->deposit($box, 10000.00, '2026-01-05');

        $yield = $this->service->calculateYield($box->fresh());

        // Meio CDI não é meio rendimento: a taxa anual cai pela metade, mas a
        // capitalização diária é logarítmica, então em 20 dias sobra um pouco
        // mais que a metade (51,04 contra 49,62).
        $this->assertEqualsWithDelta(51.0407846572, $yield, 0.0000001);
        $this->assertGreaterThan(99.2412536769 / 2, $yield);
        $this->assertLessThan(99.2412536769, $yield);
    }

    public function test_a_withdrawal_subtracts_its_own_yield(): void
    {
        $box = $this->box();
        $this->deposit($box, 10000.00, '2026-01-05');
        $this->withdraw($box, 5000.00, '2026-01-05');

        // Mesmo prazo, metade do valor: sobra metade do rendimento.
        $this->assertEqualsWithDelta(49.6206268384, $this->service->calculateYield($box->fresh()), 0.0000001);
    }

    public function test_the_yield_is_never_negative(): void
    {
        $box = $this->box();
        // Saque antigo e depósito recente: a conta daria negativo.
        $this->withdraw($box, 10000.00, '2026-01-05');
        $this->deposit($box, 10000.00, '2026-01-26');

        $this->assertSame(0.0, $this->service->calculateYield($box->fresh()));
    }

    public function test_the_total_is_the_balance_plus_the_yield(): void
    {
        $box = $this->box();
        $this->deposit($box, 10000.00, '2026-01-05');

        $this->assertEqualsWithDelta(10099.2412536769, $this->service->totalWithYield($box->fresh()), 0.0000001);
    }

    private function box(array $attrs = []): InvestmentBox
    {
        return InvestmentBox::create(array_merge([
            'name' => 'Reserva',
            'rate_percentage' => 100.00,
            'max_amount' => null,
            'is_active' => true,
        ], $attrs));
    }

    private function deposit(InvestmentBox $box, float $amount, string $date): void
    {
        $this->movement($box, 'deposit', $amount, $date);
    }

    private function withdraw(InvestmentBox $box, float $amount, string $date): void
    {
        $this->movement($box, 'withdrawal', $amount, $date);
    }

    private function movement(InvestmentBox $box, string $type, float $amount, string $date): void
    {
        InvestmentTransaction::create([
            'investment_box_id' => $box->id,
            'type' => $type,
            'amount' => $amount,
            'transacted_at' => $date,
        ]);
    }
}
