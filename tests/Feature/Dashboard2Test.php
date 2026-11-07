<?php

namespace Tests\Feature;

use App\Models\BalanceSnapshot;
use App\Models\CreditCard;
use App\Models\CreditCardPurchase;
use App\Models\FixedExpense;
use App\Models\InvestmentBox;
use App\Models\InvestmentTransaction;
use App\Models\Salary;
use App\Models\VariableExpense;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Segunda versão da tela inicial, lado a lado com a primeira. Ela não refaz a
 * projeção: organiza o que o ProjectionService devolve em ciclos, indicadores e
 * detalhe por mês. Os ciclos de referência são os de out/2026 (30/09 a 29/10)
 * e nov/2026 (30/10 a 27/11).
 */
class Dashboard2Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
    }

    public function test_the_last_day_of_a_cycle_still_belongs_to_that_cycle(): void
    {
        // 29/10 é o penúltimo dia útil: o salário de novembro só cai amanhã.
        Carbon::setTestNow('2026-10-29');

        $cycles = $this->unlocked()->get('/dashboard2')->viewData('cycles');

        $this->assertSame('2026-10', $cycles->keys()->first());
        $this->assertTrue($cycles->first()['is_current']);
    }

    public function test_salary_day_opens_the_next_cycle(): void
    {
        Carbon::setTestNow('2026-10-30');

        $cycles = $this->unlocked()->get('/dashboard2')->viewData('cycles');

        $this->assertSame('2026-11', $cycles->keys()->first());
        $this->assertTrue($cycles->first()['is_current']);
    }

    public function test_the_dashboard_never_calls_pluggy(): void
    {
        Carbon::setTestNow('2026-10-15');
        $this->givenABalance(total: 1000.00);

        $this->unlocked()->get('/dashboard2')->assertOk();

        // A consulta à API acontece quando o usuário manda atualizar, não ao abrir a tela.
        Http::assertNothingSent();
    }

    public function test_before_the_first_import_the_chain_starts_from_the_simulated_boxes(): void
    {
        Carbon::setTestNow('2026-10-15');
        $box = InvestmentBox::create(['name' => 'Reserva', 'rate_percentage' => 100, 'is_active' => true]);
        // Depositado hoje: ainda não rendeu nada, o total é o próprio depósito.
        InvestmentTransaction::create([
            'investment_box_id' => $box->id,
            'type' => 'deposit',
            'amount' => 500.00,
            'transacted_at' => '2026-10-15',
        ]);

        $response = $this->unlocked()->get('/dashboard2');

        $response->assertOk();
        $this->assertNull($response->viewData('balanceSnapshot'));
        $this->assertEquals(500.00, $response->viewData('anchor'));
    }

    public function test_the_lowest_balance_is_the_deepest_point_of_the_chain(): void
    {
        Carbon::setTestNow('2026-10-15');
        Salary::create(['amount' => 5000.00, 'effective_from' => '2025-01-01']);
        $this->givenABalance(total: 1000.00);
        VariableExpense::create(['description' => 'Reforma', 'amount' => 9000.00, 'paid_at' => '2026-11-10']);
        VariableExpense::create(['description' => 'Viagem', 'amount' => 8000.00, 'paid_at' => '2026-12-10']);

        $response = $this->unlocked()->get('/dashboard2?months=6');

        // Saldo em conta: out 1000, nov −3000, dez −6000, jan −1000, fev 4000, mar 9000.
        // O pior resultado de ciclo é novembro (−4000), mas o fundo do poço é dezembro.
        $lowest = $response->viewData('lowest');
        $this->assertSame('2026-12', $lowest['month']->format('Y-m'));
        $this->assertEquals(-6000.00, $lowest['value']);

        $end = $response->viewData('horizonEnd');
        $this->assertSame('2027-03', $end['month']->format('Y-m'));
        $this->assertEquals(9000.00, $end['value']);
    }

    public function test_each_card_bill_is_summed_on_its_own(): void
    {
        Carbon::setTestNow('2026-10-15');
        // Fecham dia 25 e vencem dia 5: compra de setembro é paga em outubro.
        $nubank = $this->card('Nubank - Mastercard');
        $inter = $this->card('Inter - Visa');
        $this->purchase($nubank, ['total_amount' => 300.00, 'installments' => 3, 'purchased_at' => '2026-09-10']);
        $this->purchase($nubank, ['total_amount' => 50.00, 'installments' => 1, 'purchased_at' => '2026-09-12']);
        $this->purchase($inter, ['total_amount' => 400.00, 'installments' => 1, 'purchased_at' => '2026-09-15']);

        $cards = $this->unlocked()->get('/dashboard2')->viewData('cycles')['2026-10']['cards'];

        $totals = $cards->pluck('total', 'name');
        $this->assertCount(2, $totals);
        $this->assertEquals(150.00, $totals['Nubank - Mastercard']); // parcela de 100 + 50
        $this->assertEquals(400.00, $totals['Inter - Visa']);
    }

    public function test_the_committed_share_is_expenses_over_income(): void
    {
        Carbon::setTestNow('2026-10-15');
        Salary::create(['amount' => 5000.00, 'effective_from' => '2025-01-01']);
        FixedExpense::create(['description' => 'Internet', 'amount' => 1000.00, 'is_active' => true]);
        VariableExpense::create(['description' => 'Mercado', 'amount' => 3000.00, 'paid_at' => '2026-11-10']);

        $november = $this->unlocked()->get('/dashboard2')->viewData('cycles')['2026-11'];

        $this->assertEqualsWithDelta(0.8, $november['committed'], 0.0001); // 4000 de 5000
    }

    public function test_a_cycle_without_income_has_no_committed_share(): void
    {
        Carbon::setTestNow('2026-10-15');
        FixedExpense::create(['description' => 'Internet', 'amount' => 1000.00, 'is_active' => true]);

        $response = $this->unlocked()->get('/dashboard2');

        $response->assertOk();
        $this->assertNull($response->viewData('cycles')['2026-11']['committed']);
    }

    public function test_an_empty_horizon_still_renders(): void
    {
        Carbon::setTestNow('2026-10-15');

        // Sem lançamento nenhum, todos os saldos são zero: a escala do gráfico
        // não pode depender de haver variação entre eles.
        $this->unlocked()->get('/dashboard2?months=1')->assertOk();
    }

    public function test_a_negative_cycle_result_keeps_its_minus_sign(): void
    {
        Carbon::setTestNow('2026-10-15');
        Salary::create(['amount' => 1000.00, 'effective_from' => '2025-01-01']);
        FixedExpense::create(['description' => 'Aluguel', 'amount' => 3000.00, 'is_active' => true]);

        $this->unlocked()->get('/dashboard2')->assertSee('−R$ 2.000,00');
    }

    public function test_html_in_a_description_is_shown_as_text(): void
    {
        Carbon::setTestNow('2026-10-15');
        $description = '<img src=x onerror=alert(1)>';
        VariableExpense::create(['description' => $description, 'amount' => 10.00, 'paid_at' => '2026-10-20']);

        $response = $this->unlocked()->get('/dashboard2');

        // Descrições vêm do extrato: aparecem como texto, nunca como marcação.
        $response->assertSee($description);
        $response->assertDontSee($description, false);
    }

    public function test_the_starting_balance_says_when_it_was_read_and_how_it_splits(): void
    {
        Carbon::setTestNow('2026-10-09 20:00');
        $this->givenABalance(total: 4969.47, checking: 2123.34, boxes: 2846.13, syncedAt: '2026-10-01 23:02:00');

        $response = $this->unlocked()->get('/dashboard2');

        // O saldo é de 01/10, não de hoje: a tela precisa dizer isso junto do número.
        $response->assertSee('01/10 às 23:02');
        $response->assertSee('R$ 2.123,34');
        $response->assertSee('R$ 2.846,13');
        $this->assertEquals(4969.47, $response->viewData('anchor'));
    }

    public function test_a_reading_from_the_previous_cycle_leaves_this_cycles_salary_pending(): void
    {
        Carbon::setTestNow('2026-10-09');
        Salary::create(['amount' => 5000.00, 'effective_from' => '2025-01-01']);
        // Lida em 09/09, no ciclo de setembro: viu o salário de setembro, não o de outubro.
        $this->givenABalance(total: 1000.00, syncedAt: '2026-09-09 21:35:00');

        $october = $this->unlocked()->get('/dashboard2')->viewData('cycles')['2026-10'];

        $this->assertEquals(5000.00, $october['pending']);
        $this->assertEquals(6000.00, $october['accumulated']);
    }

    public function test_the_current_cycle_shows_what_is_still_to_happen(): void
    {
        Carbon::setTestNow('2026-10-15');
        Salary::create(['amount' => 5000.00, 'effective_from' => '2025-01-01']);
        $this->givenABalance(total: 3000.00);
        // Conta fixa ainda não vista no extrato: é o que separa o resultado do
        // ciclo (+4000) do quanto o saldo em conta ainda vai mudar (−1000).
        FixedExpense::create(['description' => 'Internet', 'amount' => 1000.00, 'is_active' => true]);

        $this->unlocked()->get('/dashboard2')->assertSeeInOrder(['Falta acontecer', '−R$ 1.000,00']);
    }

    private function givenABalance(
        float $total,
        ?float $checking = null,
        float $boxes = 0.0,
        string $syncedAt = '2026-10-15 08:00:00',
    ): void {
        BalanceSnapshot::create([
            'checking' => $checking ?? $total - $boxes,
            'boxes' => $boxes,
            'total' => $total,
            'salary_credited' => true,
            'synced_at' => $syncedAt,
        ]);
    }

    private function card(string $name): CreditCard
    {
        return CreditCard::create([
            'name' => $name,
            'color' => '#820ad1',
            'is_active' => true,
            'closing_day' => 25,
            'due_day' => 5,
        ]);
    }

    /** is_recurring não é fillable no model, então a criação passa por forceFill. */
    private function purchase(CreditCard $card, array $attrs): CreditCardPurchase
    {
        $purchase = new CreditCardPurchase;

        $purchase->forceFill(array_merge([
            'credit_card_id' => $card->id,
            'description' => 'Compra',
            'installments' => 1,
            'is_recurring' => false,
        ], $attrs))->save();

        return $purchase;
    }
}
