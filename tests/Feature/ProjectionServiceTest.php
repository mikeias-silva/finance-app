<?php

namespace Tests\Feature;

use App\Models\CreditCard;
use App\Models\CreditCardPurchase;
use App\Models\FixedExpense;
use App\Models\InvestmentBox;
use App\Models\InvestmentTransaction;
use App\Models\Salary;
use App\Models\StatementPayment;
use App\Models\VariableExpense;
use App\Models\VariableIncome;
use App\Services\CaixaBillService;
use App\Services\ProjectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

/**
 * A projeção é o produto do app: para cada ciclo salarial, quanto entra, quanto
 * já está comprometido e quanto sobra acumulado ao longo do tempo. Os testes usam
 * o ciclo de junho/2026 (29/05 a 29/06) como referência.
 */
class ProjectionServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProjectionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ProjectionService;
    }

    public function test_each_month_carries_its_salary_cycle_as_the_period(): void
    {
        $june = $this->project('2026-06')['2026-06'];

        $this->assertSame('2026-05-29', $june['period_start']->toDateString());
        $this->assertSame('2026-06-29', $june['period_end']->toDateString());
    }

    public function test_the_salary_in_force_at_the_start_of_the_month_is_the_one_used(): void
    {
        Salary::create(['amount' => 5000.00, 'effective_from' => '2025-01-01']);
        Salary::create(['amount' => 6000.00, 'effective_from' => '2026-06-01']);

        $projection = $this->project('2026-05', '2026-06');

        $this->assertEquals(5000.00, $projection['2026-05']['salary']);
        $this->assertEquals(6000.00, $projection['2026-06']['salary']);
    }

    public function test_without_a_registered_salary_the_month_has_no_income(): void
    {
        $this->assertEquals(0, $this->project('2026-06')['2026-06']['salary']);
    }

    public function test_only_active_fixed_expenses_still_within_validity_are_counted(): void
    {
        FixedExpense::create(['description' => 'Internet', 'amount' => 100.00, 'is_active' => true]);
        FixedExpense::create(['description' => 'Academia', 'amount' => 80.00, 'is_active' => false]);
        FixedExpense::create(['description' => 'IPTU', 'amount' => 200.00, 'is_active' => true, 'valid_until' => '2026-06-15']);

        $projection = $this->project('2026-06', '2026-07');

        // A validade vale o mês inteiro: 15/06 ainda cobre junho.
        $this->assertEqualsCanonicalizing(
            ['Internet', 'IPTU'],
            $projection['2026-06']['fixed_expenses']->pluck('description')->all()
        );
        $this->assertSame(['Internet'], $projection['2026-07']['fixed_expenses']->pluck('description')->values()->all());
    }

    public function test_variable_expenses_are_bounded_by_the_cycle_not_the_calendar_month(): void
    {
        VariableExpense::create(['description' => 'véspera', 'amount' => 10, 'paid_at' => '2026-05-28']);
        VariableExpense::create(['description' => 'primeiro dia', 'amount' => 20, 'paid_at' => '2026-05-29']);
        VariableExpense::create(['description' => 'último dia', 'amount' => 30, 'paid_at' => '2026-06-29']);
        VariableExpense::create(['description' => 'próximo ciclo', 'amount' => 40, 'paid_at' => '2026-06-30']);

        $june = $this->project('2026-06')['2026-06'];

        $this->assertSame(
            ['primeiro dia', 'último dia'],
            $june['variable_expenses']->pluck('description')->all()
        );
    }

    public function test_variable_incomes_are_bounded_by_the_same_cycle(): void
    {
        VariableIncome::create(['description' => 'véspera', 'amount' => 10, 'received_at' => '2026-05-28']);
        VariableIncome::create(['description' => 'primeiro dia', 'amount' => 20, 'received_at' => '2026-05-29']);
        VariableIncome::create(['description' => 'último dia', 'amount' => 30, 'received_at' => '2026-06-29']);
        VariableIncome::create(['description' => 'próximo ciclo', 'amount' => 40, 'received_at' => '2026-06-30']);

        $june = $this->project('2026-06')['2026-06'];

        $this->assertSame(
            ['primeiro dia', 'último dia'],
            $june['variable_income']->pluck('description')->all()
        );
    }

    public function test_an_instalment_lands_on_the_month_its_bill_is_paid(): void
    {
        // Fecha dia 25 e vence dia 5: o vencimento cai no mês seguinte ao fechamento.
        $card = $this->card(['closing_day' => 25, 'due_day' => 5]);
        $this->purchase($card, ['total_amount' => 300.00, 'installments' => 3, 'purchased_at' => '2026-06-10']);

        $projection = $this->project('2026-06', '2026-09');

        $this->assertCount(0, $projection['2026-06']['card_expenses']);
        $this->assertSame(1, $projection['2026-07']['card_expenses'][0]['current_installment']);
        $this->assertSame(2, $projection['2026-08']['card_expenses'][0]['current_installment']);
        $this->assertSame(3, $projection['2026-09']['card_expenses'][0]['current_installment']);
        $this->assertSame(100.00, $projection['2026-07']['card_expenses'][0]['amount']);
    }

    public function test_a_purchase_after_the_closing_day_only_shows_up_a_bill_later(): void
    {
        $card = $this->card(['closing_day' => 25, 'due_day' => 5]);
        $this->purchase($card, ['total_amount' => 100.00, 'installments' => 1, 'purchased_at' => '2026-06-26']);

        $projection = $this->project('2026-06', '2026-08');

        $this->assertCount(0, $projection['2026-07']['card_expenses']);
        $this->assertCount(1, $projection['2026-08']['card_expenses']);
    }

    public function test_a_recurring_charge_repeats_every_month_with_the_full_amount(): void
    {
        $card = $this->card(['closing_day' => 25, 'due_day' => 5]);
        $this->purchase($card, [
            'description' => 'Streaming',
            'total_amount' => 49.90,
            'installments' => 1,
            'is_recurring' => true,
            'purchased_at' => '2026-06-10',
        ]);

        $projection = $this->project('2026-07', '2026-09');

        foreach (['2026-07', '2026-08', '2026-09'] as $month) {
            $this->assertCount(1, $projection[$month]['card_expenses'], $month);
            // Recorrente não divide pelo número de parcelas.
            $this->assertEquals(49.90, $projection[$month]['card_expenses'][0]['amount'], $month);
            $this->assertTrue((bool) $projection[$month]['card_expenses'][0]['is_recurring']);
        }
    }

    public function test_the_caixa_card_charges_one_instalment_per_bill_and_a_skipped_month_does_not_pile_up(): void
    {
        // Cenário real: o vencimento mudou e 07/2026 ficou sem fatura.
        $card = $this->card([
            'name' => 'Caixa - Visa',
            'closing_day' => 22,
            'due_day' => 1,
            'billing_skip_months' => ['2026-07'],
        ]);
        $this->purchase($card, ['total_amount' => 300.00, 'installments' => 3, 'purchased_at' => '2026-06-10']);

        $projection = $this->project('2026-06', '2026-11');

        $this->assertCount(0, $projection['2026-07']['card_expenses'], 'mês sem fatura');
        // A sequência apenas desliza: agosto não recebe duas parcelas de carona.
        $this->assertSame([1], $projection['2026-08']['card_expenses']->pluck('current_installment')->all());
        $this->assertSame([2], $projection['2026-09']['card_expenses']->pluck('current_installment')->all());
        $this->assertSame([3], $projection['2026-10']['card_expenses']->pluck('current_installment')->all());
        $this->assertCount(0, $projection['2026-11']['card_expenses'], 'parcelamento acabou');
    }

    public function test_the_balance_is_income_minus_expenses_and_saldo_cc_discounts_the_boxes(): void
    {
        Salary::create(['amount' => 5000.00, 'effective_from' => '2025-01-01']);
        FixedExpense::create(['description' => 'Internet', 'amount' => 1000.00, 'is_active' => true]);
        VariableExpense::create(['description' => 'Mercado', 'amount' => 200.00, 'paid_at' => '2026-06-10']);
        VariableIncome::create(['description' => 'Freela', 'amount' => 300.00, 'received_at' => '2026-06-10']);

        $card = $this->card(['closing_day' => 25, 'due_day' => 5]);
        $this->purchase($card, ['total_amount' => 100.00, 'installments' => 1, 'purchased_at' => '2026-05-10']);

        $this->boxMovement('deposit', 500.00, '2026-06-10');
        $this->boxMovement('withdrawal', 100.00, '2026-06-11');

        $june = $this->project('2026-06')['2026-06'];

        $this->assertEquals(4000.00, $june['balance']);   // 5300 − 1300
        $this->assertEquals(400.00, $june['rdb_net']);    // 500 − 100
        $this->assertEquals(3600.00, $june['saldo_cc']);  // sobra fora das caixinhas
    }

    public function test_rdb_movements_outside_the_cycle_do_not_count(): void
    {
        $this->boxMovement('deposit', 500.00, '2026-06-29');
        $this->boxMovement('deposit', 900.00, '2026-06-30');

        $this->assertEquals(500.00, $this->project('2026-06')['2026-06']['rdb_net']);
    }

    public function test_a_future_month_adds_its_whole_balance_to_the_anchor(): void
    {
        Carbon::setTestNow('2026-06-15');
        Salary::create(['amount' => 5000.00, 'effective_from' => '2025-01-01']);
        FixedExpense::create(['description' => 'Internet', 'amount' => 1000.00, 'is_active' => true]);

        $july = $this->service->accumulate($this->project('2026-07'), 200.00)['2026-07'];

        $this->assertEquals(4000.00, $july['pending']);
        $this->assertEquals(4200.00, $july['accumulated']);
    }

    public function test_the_current_cycle_only_adds_what_is_still_going_to_happen(): void
    {
        Carbon::setTestNow('2026-06-15');

        // O salário caiu em 29/05, no início do ciclo: já está na âncora.
        Salary::create(['amount' => 5000.00, 'effective_from' => '2025-01-01']);
        FixedExpense::create(['description' => 'Internet', 'amount' => 1000.00, 'is_active' => true]);
        VariableIncome::create(['description' => 'recebido', 'amount' => 100.00, 'received_at' => '2026-06-01']);
        VariableIncome::create(['description' => 'a receber', 'amount' => 300.00, 'received_at' => '2026-06-20']);
        VariableExpense::create(['description' => 'pago', 'amount' => 50.00, 'paid_at' => '2026-06-01']);
        VariableExpense::create(['description' => 'a pagar', 'amount' => 200.00, 'paid_at' => '2026-06-20']);

        $june = $this->service->accumulate($this->project('2026-06'), 7000.00)['2026-06'];

        // 300 a receber − 200 a pagar − 1000 de conta fixa ainda não identificada.
        $this->assertEquals(-900.00, $june['pending']);
        $this->assertEquals(6100.00, $june['accumulated']);
    }

    public function test_a_fixed_expense_already_seen_in_the_statement_stops_being_pending(): void
    {
        Carbon::setTestNow('2026-06-15');
        $internet = FixedExpense::create(['description' => 'Internet', 'amount' => 1000.00, 'is_active' => true]);
        StatementPayment::create([
            'label' => 'Internet',
            'fixed_expense_id' => $internet->id,
            'amount' => 1000.00,
            'paid_at' => '2026-06-05',
            'identifier' => 'pluggy:tx-internet',
        ]);

        $june = $this->service->accumulate($this->project('2026-06'), 0.0)['2026-06'];

        $this->assertEquals(0.0, $june['pending']);
    }

    public function test_a_payment_on_the_last_day_of_the_cycle_still_counts(): void
    {
        Carbon::setTestNow('2026-06-15');
        $internet = FixedExpense::create(['description' => 'Internet', 'amount' => 1000.00, 'is_active' => true]);
        StatementPayment::create([
            'label' => 'Internet',
            'fixed_expense_id' => $internet->id,
            'amount' => 1000.00,
            'paid_at' => '2026-06-29', // último dia do ciclo
            'identifier' => 'pluggy:tx-internet',
        ]);

        $this->assertEquals(0.0, $this->service->accumulate($this->project('2026-06'), 0.0)['2026-06']['pending']);
    }

    public function test_a_settled_card_bill_stops_being_pending(): void
    {
        Carbon::setTestNow('2026-06-15');
        $card = $this->card(['closing_day' => 25, 'due_day' => 5]);
        $this->purchase($card, ['total_amount' => 400.00, 'installments' => 1, 'purchased_at' => '2026-05-10']);

        $accumulated = fn () => $this->service->accumulate($this->project('2026-06'), 0.0)['2026-06']['pending'];

        $this->assertEquals(-400.00, $accumulated());

        StatementPayment::create([
            'label' => 'Pagamento Fatura Nubank',
            'credit_card_id' => $card->id,
            'amount' => 400.00,
            'paid_at' => '2026-06-05',
            'identifier' => 'pluggy:tx-fatura',
        ]);

        $this->assertEquals(0.0, $accumulated());
    }

    /**
     * O salário é a maior entrada do ciclo e cai no primeiro dia dele. Tirá-lo do
     * pendente por data — "é dia 29, então já caiu" — cria um buraco de um salário
     * inteiro no dia em que ele cai: o saldo lido do Pluggy é o de antes do crédito,
     * e o pendente já o considerava recebido. Some das duas pontas.
     */
    public function test_the_salary_keeps_counting_as_pending_until_the_credit_shows_up(): void
    {
        Carbon::setTestNow('2026-05-29'); // primeiro dia do ciclo, quando o salário cai
        Salary::create(['amount' => 5000.00, 'effective_from' => '2025-01-01']);

        $june = $this->service->accumulate($this->project('2026-06'), 1000.00)['2026-06'];

        $this->assertEquals(5000.00, $june['pending']);
        $this->assertEquals(6000.00, $june['accumulated']);
    }

    public function test_the_credit_identified_in_the_statement_takes_the_salary_out_of_the_pending(): void
    {
        Carbon::setTestNow('2026-05-29');
        Salary::create(['amount' => 5000.00, 'effective_from' => '2025-01-01']);

        // A âncora já subiu com o crédito: somá-lo de novo contaria duas vezes.
        $june = $this->service->accumulate($this->project('2026-06'), 6000.00, true)['2026-06'];

        $this->assertEquals(0.0, $june['pending']);
        $this->assertEquals(6000.00, $june['accumulated']);
    }

    public function test_the_statement_answer_wins_over_the_calendar(): void
    {
        Carbon::setTestNow('2026-06-15'); // muito depois do dia do salário
        Salary::create(['amount' => 5000.00, 'effective_from' => '2025-01-01']);

        $june = $this->service->accumulate($this->project('2026-06'), 0.0, false)['2026-06'];

        $this->assertEquals(5000.00, $june['pending']);
    }

    public function test_without_an_answer_the_salary_only_counts_as_received_from_the_next_day_on(): void
    {
        Carbon::setTestNow('2026-05-30'); // dia seguinte ao crédito
        Salary::create(['amount' => 5000.00, 'effective_from' => '2025-01-01']);

        $june = $this->service->accumulate($this->project('2026-06'), 6000.00)['2026-06'];

        $this->assertEquals(0.0, $june['pending']);
    }

    /** O invariante que o bug quebrava: virar o dia, sozinho, não move a projeção. */
    public function test_turning_the_day_alone_does_not_move_the_accumulated(): void
    {
        Salary::create(['amount' => 5000.00, 'effective_from' => '2025-01-01']);
        FixedExpense::create(['description' => 'Internet', 'amount' => 1000.00, 'is_active' => true]);

        Carbon::setTestNow('2026-05-28'); // véspera: junho ainda é mês futuro
        $before = $this->service->accumulate($this->project('2026-06', '2026-08'), 1000.00);

        Carbon::setTestNow('2026-05-29'); // junho vira o ciclo corrente
        $after = $this->service->accumulate($this->project('2026-06', '2026-08'), 1000.00);

        $this->assertEquals($before['2026-08']['accumulated'], $after['2026-08']['accumulated']);
    }

    public function test_an_income_dated_today_only_leaves_the_pending_when_it_came_from_the_statement(): void
    {
        Carbon::setTestNow('2026-06-15');
        VariableIncome::create(['description' => 'Freela', 'amount' => 300.00, 'received_at' => '2026-06-15']);

        $pending = fn () => $this->service->accumulate($this->project('2026-06'), 0.0)['2026-06']['pending'];

        // Cadastrado à mão: ainda vai cair, o saldo real não o tem.
        $this->assertEquals(300.00, $pending());

        VariableIncome::query()->update(['identifier' => 'pluggy:tx-freela']);

        // Veio do extrato: já está no saldo que serve de âncora.
        $this->assertEquals(0.0, $pending());
    }

    public function test_an_expense_dated_today_follows_the_same_rule(): void
    {
        Carbon::setTestNow('2026-06-15');
        VariableExpense::create(['description' => 'Mercado', 'amount' => 200.00, 'paid_at' => '2026-06-15']);

        $pending = fn () => $this->service->accumulate($this->project('2026-06'), 0.0)['2026-06']['pending'];

        $this->assertEquals(-200.00, $pending());

        VariableExpense::query()->update(['identifier' => 'pluggy:tx-mercado']);

        $this->assertEquals(0.0, $pending());
    }

    public function test_the_merge_only_adds_what_the_api_has_not_published_yet(): void
    {
        $caixa = $this->card(['name' => 'Caixa - Visa', 'closing_day' => 25, 'due_day' => 5]);
        // Já publicada pela API: mesma data, mesmo valor de parcela, mesmas parcelas.
        $this->purchase($caixa, ['description' => 'Tênis', 'total_amount' => 300.00, 'installments' => 3, 'purchased_at' => '2026-05-10']);
        // Só no cadastro manual: a Caixa ainda não publicou.
        $this->purchase($caixa, ['description' => 'Livro', 'total_amount' => 60.00, 'installments' => 1, 'purchased_at' => '2026-05-12']);
        // Recorrente: a API projeta por conta própria, com outro nome.
        $this->purchase($caixa, ['description' => 'Gympass', 'total_amount' => 49.90, 'installments' => 1, 'is_recurring' => true, 'purchased_at' => '2026-05-10']);

        $bills = $this->bills(['tryResolve' => [
            'bill' => ['totalAmount' => 500.00],
            'period' => ['start' => '2026-05-01', 'end' => '2026-05-25'],
            'transactions' => [
                $this->apiTransaction('Tenis Loja', 100.00, '2026-05-10', 3),
                $this->apiTransaction('Wellhub', 49.90, '2026-05-10', 1),
            ],
        ]]);

        $merged = $this->service->mergedCaixaItems(Carbon::parse('2026-06-01'), $bills);

        $this->assertSame(500.00, $merged['total_from_api']);
        $this->assertSame(60.00, $merged['total_only_in_db']);
        $this->assertSame(560.00, $merged['total']);
        $this->assertSame(
            ['Livro'],
            collect($merged['transactions'])->where('from_db', true)->pluck('description')->all()
        );
    }

    public function test_an_entry_with_a_different_date_still_matches_by_instalment_amount(): void
    {
        $caixa = $this->card(['name' => 'Caixa - Visa', 'closing_day' => 25, 'due_day' => 5]);
        // Cadastrada com data diferente da que a API reporta.
        $this->purchase($caixa, ['description' => 'Tênis', 'total_amount' => 100.00, 'installments' => 1, 'purchased_at' => '2026-05-09']);

        $bills = $this->bills(['tryResolve' => [
            'bill' => ['totalAmount' => 100.00],
            'period' => ['start' => '2026-05-01', 'end' => '2026-05-25'],
            'transactions' => [$this->apiTransaction('Tenis Loja', 100.00, '2026-05-10', 1)],
        ]]);

        $merged = $this->service->mergedCaixaItems(Carbon::parse('2026-06-01'), $bills);

        // O segundo casamento, só pelo valor, evita contar a mesma compra duas vezes.
        $this->assertSame(0.0, $merged['total_only_in_db']);
        $this->assertSame(100.00, $merged['total']);
    }

    private function project(string $from, ?string $to = null): Collection
    {
        return $this->service->project(
            Carbon::parse($from.'-01'),
            Carbon::parse(($to ?? $from).'-01'),
        );
    }

    private function card(array $attrs = []): CreditCard
    {
        return CreditCard::create(array_merge([
            'name' => 'Nubank - Mastercard',
            'color' => '#820ad1',
            'is_active' => true,
            'closing_day' => 25,
            'due_day' => 5,
        ], $attrs));
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

    private function boxMovement(string $type, float $amount, string $date): void
    {
        $box = InvestmentBox::firstOrCreate(
            ['name' => 'Reserva'],
            ['rate_percentage' => 100.00, 'is_active' => true],
        );

        InvestmentTransaction::create([
            'investment_box_id' => $box->id,
            'type' => $type,
            'amount' => $amount,
            'transacted_at' => $date,
        ]);
    }

    /** Dublê do service que fala com o Pluggy — aqui só interessa o que ele devolve. */
    private function bills(array $returns): CaixaBillService
    {
        $mock = Mockery::mock(CaixaBillService::class);

        foreach ($returns as $method => $value) {
            $mock->shouldReceive($method)->andReturn($value);
        }

        return $mock;
    }

    /** Lançamento de cartão como a API do Pluggy devolve. */
    private function apiTransaction(string $description, float $amount, string $purchaseDate, int $installments): array
    {
        return [
            'description' => $description,
            'amount' => $amount,
            'creditCardMetadata' => [
                'purchaseDate' => $purchaseDate.'T00:00:00.000Z',
                'totalInstallments' => $installments,
            ],
        ];
    }
}
