<?php

namespace Tests\Feature;

use App\Models\BalanceSnapshot;
use App\Models\CreditCard;
use App\Models\CreditCardPurchase;
use App\Models\FixedExpense;
use App\Models\InvestmentBox;
use App\Models\InvestmentTransaction;
use App\Models\RecurringIncome;
use App\Models\Salary;
use App\Models\VariableExpense;
use App\Models\VariableIncome;
use App\Services\BillingPeriod;
use App\Services\NubankImportException;
use App\Services\NubankImportOptions;
use App\Services\NubankImportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * A classificação e a reconciliação saíram do comando para o service, e é este
 * teste que garante que continuam iguais — o comando e o botão do dashboard só
 * chamam import().
 */
class NubankImportServiceTest extends TestCase
{
    use RefreshDatabase;

    /** Data dentro do ciclo salarial atual, o período padrão da importação. */
    private string $date;

    /** Primeiro dia do ciclo — o dia em que o salário cai. */
    private string $salaryDate;

    protected function setUp(): void
    {
        parent::setUp();

        config(['pluggy.items.nubank' => 'item-teste']);

        $this->date = BillingPeriod::for(now())['start']->copy()->addDay()->toDateString();
    }

    public function test_it_creates_variable_expenses_and_incomes_from_the_statement(): void
    {
        $this->fakePluggy([
            $this->transaction('tx-1', -150.75, 'Compra no débito|PADARIA'),
            $this->transaction('tx-2', 320.00, 'Transferência recebida|FULANO'),
        ]);

        $result = $this->import();

        $this->assertDatabaseHas('variable_expenses', [
            'identifier' => 'pluggy:tx-1',
            'description' => 'Compra no débito|PADARIA',
            'amount' => 150.75,
        ]);
        $this->assertDatabaseHas('variable_incomes', [
            'identifier' => 'pluggy:tx-2',
            'description' => 'Transferência recebida|FULANO',
            'amount' => 320.00,
        ]);
        $this->assertSame(2, $result->created);
        $this->assertSame(0, $result->skipped);
        $this->assertSame(
            [
                ['type' => 'expense', 'date' => $this->date, 'amount' => 150.75, 'description' => 'Compra no débito|PADARIA'],
                ['type' => 'income', 'date' => $this->date, 'amount' => 320.00, 'description' => 'Transferência recebida|FULANO'],
            ],
            $result->entries
        );
    }

    public function test_it_does_not_duplicate_a_transaction_already_in_the_database(): void
    {
        VariableExpense::create([
            'description' => 'Lançamento vindo do CSV',
            'amount' => 150.75,
            'paid_at' => $this->date,
        ]);

        $this->fakePluggy([
            $this->transaction('tx-1', -150.75, 'Compra no débito|PADARIA'),
        ]);

        $result = $this->import();

        // Reconcilia por (data, valor): o id do CSV é do Nubank, o da API é do Pluggy.
        $this->assertSame(0, $result->created);
        $this->assertSame(1, $result->skipped);
        $this->assertSame(1, VariableExpense::count());
    }

    public function test_a_fixed_expense_paid_above_the_forecast_generates_an_adjustment(): void
    {
        FixedExpense::create([
            'description' => 'Tim',
            'amount' => 100.00,
            'is_active' => true,
        ]);

        $this->fakePluggy([
            $this->transaction('tx-1', -130.00, 'Transferência enviada|TIM S A'),
        ]);

        $result = $this->import();

        $this->assertDatabaseHas('variable_expenses', [
            'description' => 'Ajuste Tim',
            'amount' => 30.00,
        ]);
        $this->assertDatabaseHas('statement_payments', [
            'identifier' => 'pluggy:tx-1',
            'label' => 'Tim',
            'amount' => 130.00,
        ]);
        $this->assertSame(1, $result->confirmed);
        // O mesmo fato que vira uma linha no console alimenta a tabela do dashboard.
        $this->assertSame([
            'label' => 'Tim',
            'date' => $this->date,
            'paid' => 130.00,
            'expected' => 100.00,
            'difference' => 30.00,
        ], $result->fixedExpenses[0]);
    }

    public function test_the_salary_credit_does_not_become_a_variable_income(): void
    {
        $this->givenASalaryOf(5000.00);
        $this->fakePluggy([$this->salaryTransaction(5000.00)]);

        $result = $this->import();

        // A projeção já conta o salário por conta própria; importá-lo de novo
        // como receita variável somaria o mesmo dinheiro duas vezes.
        $this->assertSame(0, VariableIncome::count());
        $this->assertSame(0, $result->created);
        $this->assertSame(1, $result->confirmed);
        $this->assertSame([
            'date' => $this->salaryDate,
            'received' => 5000.00,
            'expected' => 5000.00,
            'difference' => 0.0,
        ], $result->salary);
    }

    public function test_a_salary_above_the_forecast_credits_only_the_difference(): void
    {
        $this->givenASalaryOf(5000.00);
        $this->fakePluggy([$this->salaryTransaction(5200.00)]);

        $result = $this->import();

        $this->assertDatabaseHas('variable_incomes', [
            'description' => 'Ajuste Salário',
            'amount' => 200.00,
            'received_at' => $this->salaryDate,
        ]);
        $this->assertSame(1, VariableIncome::count());
        $this->assertSame(200.00, $result->salary['difference']);
    }

    public function test_a_recurring_income_paid_with_the_salary_does_not_become_a_variable_income(): void
    {
        $this->givenASalaryOf(5000.00);
        $this->givenARecurringIncomeOf(900.00);
        $this->fakePluggy([$this->salaryTransaction(5900.00)]);

        $result = $this->import();

        // 5.900 = 5.000 de salário + 900 que a projeção já soma como recorrente.
        $this->assertSame(0, VariableIncome::count());
        $this->assertSame(5900.00, $result->salary['expected']);
        $this->assertSame(0.0, $result->salary['difference']);
    }

    public function test_only_what_exceeds_salary_and_recurring_income_becomes_a_variable_income(): void
    {
        $this->givenASalaryOf(5000.00);
        $this->givenARecurringIncomeOf(800.00);
        $this->fakePluggy([$this->salaryTransaction(5900.00)]);

        $this->import();

        $this->assertDatabaseHas('variable_incomes', ['description' => 'Ajuste Salário', 'amount' => 100.00]);
        $this->assertSame(1, VariableIncome::count());
    }

    public function test_a_small_salary_difference_is_not_mistaken_for_the_recurring_income(): void
    {
        $this->givenASalaryOf(5000.00);
        $this->givenARecurringIncomeOf(900.00);
        $this->fakePluggy([$this->salaryTransaction(5200.00)]);

        $this->import();

        // 200 a mais não é a gratificação de 900, que pode cair em crédito separado.
        $this->assertDatabaseHas('variable_incomes', ['description' => 'Ajuste Salário', 'amount' => 200.00]);
    }

    public function test_a_recurring_income_paid_on_its_own_does_not_become_a_variable_income(): void
    {
        $this->givenASalaryOf(5000.00);
        $this->givenARecurringIncomeOf(900.00);
        $this->fakePluggy([
            $this->salaryTransaction(5000.00),
            $this->transaction('tx-fa2', 900.00, 'Transferência Recebida|FA-2'),
        ]);

        $result = $this->import();

        $this->assertSame(0, VariableIncome::count());
        $this->assertSame(2, $result->confirmed);
    }

    public function test_a_credit_of_another_amount_stays_a_variable_income(): void
    {
        $this->givenASalaryOf(5000.00);
        $this->givenARecurringIncomeOf(900.00);
        $this->fakePluggy([$this->transaction('tx-1', 899.99, 'Transferência Recebida|FULANO')]);

        $this->import();

        $this->assertDatabaseHas('variable_incomes', ['identifier' => 'pluggy:tx-1', 'amount' => 899.99]);
    }

    public function test_a_recurring_income_not_yet_in_force_does_not_absorb_the_credit(): void
    {
        $this->givenASalaryOf(5000.00);
        $this->givenARecurringIncomeOf(900.00, effectiveFrom: '2030-01-01');
        $this->fakePluggy([$this->transaction('tx-1', 900.00, 'Transferência Recebida|FULANO')]);

        $this->import();

        $this->assertDatabaseHas('variable_incomes', ['identifier' => 'pluggy:tx-1', 'amount' => 900.00]);
    }

    public function test_the_condominium_paid_to_bv_garantia_only_records_the_difference(): void
    {
        $this->givenASalaryOf(5000.00);
        FixedExpense::create(['description' => 'Condomínio', 'amount' => 356.00, 'is_active' => true]);
        $this->fakePluggy([$this->transaction('tx-bv', -356.81, 'Transferência enviada pelo Pix|BV GARANTIA S.A.')]);

        $this->import();

        // O valor cheio já está na projeção como gasto fixo: só a diferença vira lançamento.
        $this->assertSame(1, VariableExpense::count());
        $this->assertDatabaseHas('variable_expenses', ['description' => 'Ajuste Condomínio', 'amount' => 0.81]);
        $this->assertDatabaseHas('statement_payments', ['label' => 'Condomínio', 'amount' => 356.81]);
    }

    public function test_a_gas_station_named_bv_stays_a_variable_expense(): void
    {
        $this->givenASalaryOf(5000.00);
        FixedExpense::create(['description' => 'Condomínio', 'amount' => 356.00, 'is_active' => true]);
        $this->fakePluggy([$this->transaction('tx-posto', -100.00, 'Compra no débito|POSTO BV LTDA')]);

        $this->import();

        $this->assertDatabaseHas('variable_expenses', ['description' => 'Compra no débito|POSTO BV LTDA', 'amount' => 100.00]);
    }

    public function test_the_pix_returning_a_third_party_installment_is_not_income(): void
    {
        $this->givenASalaryOf(5000.00);
        $this->givenAThirdPartyPurchase(purchasedAt: '2026-08-23');
        $this->fakePluggy([$this->transaction('tx-mae', 506.00, 'Transferência Recebida|MARIA')]);

        $result = $this->import();

        $this->assertSame(0, VariableIncome::count());
        $this->assertSame(1, $result->confirmed);
    }

    public function test_a_credit_of_another_value_is_still_income(): void
    {
        $this->givenASalaryOf(5000.00);
        $this->givenAThirdPartyPurchase(purchasedAt: '2026-08-23');
        $this->fakePluggy([$this->transaction('tx-1', 505.99, 'Transferência Recebida|MARIA')]);

        $this->import();

        $this->assertDatabaseHas('variable_incomes', ['identifier' => 'pluggy:tx-1']);
    }

    public function test_the_same_value_long_after_the_last_installment_is_income(): void
    {
        $this->givenASalaryOf(5000.00);
        $this->givenAThirdPartyPurchase(purchasedAt: '2024-01-10');
        $this->fakePluggy([$this->transaction('tx-1', 506.00, 'Transferência Recebida|MARIA')]);

        $this->import();

        $this->assertDatabaseHas('variable_incomes', ['identifier' => 'pluggy:tx-1']);
    }

    public function test_a_normal_purchase_of_the_same_installment_changes_nothing(): void
    {
        $this->givenASalaryOf(5000.00);
        $this->givenAThirdPartyPurchase(purchasedAt: '2026-08-23', excluded: false);
        $this->fakePluggy([$this->transaction('tx-1', 506.00, 'Transferência Recebida|MARIA')]);

        $this->import();

        $this->assertDatabaseHas('variable_incomes', ['identifier' => 'pluggy:tx-1']);
    }

    public function test_a_salary_below_the_forecast_becomes_a_variable_expense(): void
    {
        $this->givenASalaryOf(5000.00);
        $this->fakePluggy([$this->salaryTransaction(4900.00)]);

        $result = $this->import();

        $this->assertDatabaseHas('variable_expenses', [
            'description' => 'Ajuste Salário',
            'amount' => 100.00,
            'paid_at' => $this->salaryDate,
        ]);
        $this->assertSame(0, VariableIncome::count());
        $this->assertSame(-100.00, $result->salary['difference']);
    }

    public function test_the_import_records_the_real_balance_as_the_anchor(): void
    {
        $this->givenASalaryOf(5000.00);
        $this->fakePluggy([$this->salaryTransaction(5000.00)], [
            ['id' => 'pos-1', 'balance' => 700.00, 'amountOriginal' => 690.00, 'rate' => 100, 'purchaseDate' => $this->salaryDate],
        ]);

        $this->import();

        // O dashboard ancora o acumulado nisto em vez de consultar a API ao abrir.
        $snapshot = BalanceSnapshot::latestSnapshot();
        $this->assertEqualsWithDelta(1000.00, $snapshot->checking, 0.001);
        $this->assertEqualsWithDelta(700.00, $snapshot->boxes, 0.001);
        $this->assertEqualsWithDelta(1700.00, $snapshot->total, 0.001);
        $this->assertTrue($snapshot->salary_credited);
        $this->assertSame(now()->toDateTimeString(), $snapshot->synced_at->toDateTimeString());
    }

    public function test_the_snapshot_says_the_salary_is_still_pending_when_it_is_not_in_the_statement(): void
    {
        $this->givenASalaryOf(5000.00);
        $this->fakePluggy([$this->transaction('tx-1', -150.75, 'Compra no débito|PADARIA')]);

        $this->import();

        $this->assertFalse(BalanceSnapshot::latestSnapshot()->salary_credited);
    }

    public function test_an_import_that_starts_after_the_cycle_cannot_vouch_for_the_salary(): void
    {
        $this->givenASalaryOf(5000.00);
        $this->fakePluggy([]);

        // Recorte que começa depois da queda: o salário pode ter caído e estar fora
        // da janela lida. Sem evidência, o snapshot não afirma nem nega.
        $this->import(new NubankImportOptions(from: Carbon::parse($this->salaryDate)->addDays(2)));

        $this->assertNull(BalanceSnapshot::latestSnapshot()->salary_credited);
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->fakePluggy([
            $this->transaction('tx-1', -150.75, 'Compra no débito|PADARIA'),
        ]);

        $result = $this->import(new NubankImportOptions(dryRun: true));

        $this->assertSame(0, VariableExpense::count());
        $this->assertSame(0, BalanceSnapshot::count());
        // O que seria gravado continua sendo contado, para o resumo dizer quanto entraria.
        $this->assertSame(1, $result->created);
        $this->assertTrue($result->dryRun);
    }

    public function test_investment_transactions_survive_when_replace_is_off(): void
    {
        $box = InvestmentBox::create([
            'name' => 'Reserva',
            'rate_percentage' => 100,
            'is_active' => true,
        ]);
        InvestmentTransaction::create([
            'investment_box_id' => $box->id,
            'type' => 'deposit',
            'amount' => 500.00,
            'transacted_at' => $this->date,
        ]);

        $this->fakePluggy([], [
            ['id' => 'pos-1', 'balance' => 700.00, 'amountOriginal' => 690.00, 'rate' => 100, 'purchaseDate' => $this->date],
        ]);

        $result = $this->import();

        $this->assertSame(1, InvestmentTransaction::count());
        $this->assertEquals(500.00, InvestmentTransaction::first()->amount);
        $this->assertFalse($result->replacedInvestments);
        $this->assertSame([
            'name' => 'Reserva',
            'database' => 500.00,
            'api' => 690.00,
            'matches' => false,
        ], $result->investmentBoxes[0]);
    }

    public function test_replace_investments_rewrites_the_boxes_with_the_api_positions(): void
    {
        $box = InvestmentBox::create([
            'name' => 'Reserva',
            'rate_percentage' => 100,
            'is_active' => true,
        ]);
        InvestmentTransaction::create([
            'investment_box_id' => $box->id,
            'type' => 'deposit',
            'amount' => 500.00,
            'transacted_at' => $this->date,
        ]);

        $this->fakePluggy([], [
            ['id' => 'pos-1', 'balance' => 700.00, 'amountOriginal' => 690.00, 'rate' => 100, 'purchaseDate' => $this->date],
        ]);

        $result = $this->import(new NubankImportOptions(replaceInvestments: true));

        $this->assertSame(1, InvestmentTransaction::count());
        $this->assertEquals(690.00, InvestmentTransaction::first()->amount);
        $this->assertTrue($result->replacedInvestments);
        // A comparação roda antes do delete: depois dele o banco seria igual à
        // API por construção e a divergência corrigida sumiria do relatório.
        $this->assertFalse($result->investmentBoxes[0]['matches']);
        $this->assertSame(500.00, $result->investmentBoxes[0]['database']);
    }

    public function test_rdb_movements_are_left_to_the_investment_sync(): void
    {
        $this->fakePluggy([
            $this->transaction('tx-1', -200.00, 'Aplicação RDB'),
        ]);

        $result = $this->import();

        $this->assertSame(0, VariableExpense::count());
        $this->assertSame(1, $result->investmentMovements);
    }

    public function test_it_fails_when_the_pluggy_item_is_not_configured(): void
    {
        config(['pluggy.items.nubank' => null]);

        $this->expectException(NubankImportException::class);
        $this->expectExceptionMessage('PLUGGY_ITEM_ID_NUBANK não configurado no .env.');

        $this->import();
    }

    public function test_it_fails_when_there_is_no_checking_account(): void
    {
        Http::fake([
            '*/auth' => Http::response(['apiKey' => 'chave-falsa']),
            '*/accounts*' => Http::response(['results' => [['id' => 'acc-1', 'type' => 'CREDIT']]]),
        ]);

        $this->expectException(NubankImportException::class);
        $this->expectExceptionMessage('Conta corrente do Nubank não encontrada no Pluggy.');

        $this->import();
    }

    public function test_the_log_is_collected_and_streamed_at_the_same_time(): void
    {
        $this->fakePluggy([
            $this->transaction('tx-1', -150.75, 'Compra no débito|PADARIA'),
        ]);

        $streamed = [];

        $result = app(NubankImportService::class)->import(
            new NubankImportOptions,
            function ($message) use (&$streamed) {
                $streamed[] = $message->text;
            }
        );

        // O comando imprime pelo callback, a web lê $result->messages: as duas
        // portas de entrada têm que ver exatamente as mesmas linhas.
        $this->assertNotEmpty($streamed);
        $this->assertSame(
            $streamed,
            array_map(fn ($message) => $message->text, $result->messages)
        );
    }

    private function import(?NubankImportOptions $options = null)
    {
        return app(NubankImportService::class)->import($options ?? new NubankImportOptions);
    }

    private function givenAThirdPartyPurchase(string $purchasedAt, bool $excluded = true): void
    {
        CreditCardPurchase::create([
            'credit_card_id' => CreditCard::create(['name' => 'Caixa - Visa', 'closing_day' => 22, 'due_day' => 1])->id,
            'description' => 'COMPRA PARA TERCEIRO',
            'total_amount' => 5060.00,
            'installments' => 10,
            'purchased_at' => $purchasedAt,
            'excluded_from_projection' => $excluded,
        ]);
    }

    private function givenASalaryOf(float $amount): void
    {
        // 09/09/2026 cai no ciclo de setembro, que abre em 31/08 (segunda). É o
        // caso em que o dia da queda é 31 — onde a identificação do salário falhava.
        Carbon::setTestNow('2026-09-09');
        $this->salaryDate = BillingPeriod::for(now())['start']->toDateString();
        $this->date = Carbon::parse($this->salaryDate)->addDay()->toDateString();

        Salary::create(['amount' => $amount, 'effective_from' => '2025-01-01']);
    }

    private function givenARecurringIncomeOf(float $amount, string $effectiveFrom = '2025-01-01'): void
    {
        RecurringIncome::create([
            'description' => 'FA-2',
            'amount' => $amount,
            'is_active' => true,
            'effective_from' => $effectiveFrom,
        ]);
    }

    /**
     * O salário chega como transferência do próprio titular vinda de outro banco:
     * mesmo documento nas duas pontas e descrição sem nome.
     */
    private function salaryTransaction(float $amount): array
    {
        return [
            'id' => 'tx-salario',
            'date' => Carbon::parse($this->salaryDate)->toIso8601String(),
            'amount' => $amount,
            'description' => 'Transferência Recebida',
            'paymentData' => [
                'payer' => ['documentNumber' => ['value' => '123.456.789-00']],
                'receiver' => ['documentNumber' => ['value' => '12345678900']],
            ],
        ];
    }

    /** Lançamento no formato que a API do Pluggy devolve. */
    private function transaction(string $id, float $amount, string $description): array
    {
        return [
            'id' => $id,
            'date' => Carbon::parse($this->date)->toIso8601String(),
            'amount' => $amount,
            'description' => $description,
        ];
    }

    private function fakePluggy(array $transactions, array $investments = []): void
    {
        Http::fake([
            '*/auth' => Http::response(['apiKey' => 'chave-falsa']),
            '*/accounts*' => Http::response([
                'results' => [[
                    'id' => 'acc-1',
                    'type' => 'BANK',
                    'balance' => 1000.00,
                    'currencyCode' => 'BRL',
                ]],
            ]),
            '*/v2/transactions*' => Http::response(['results' => $transactions, 'next' => null]),
            '*/investments*' => Http::response(['results' => $investments, 'totalPages' => 1]),
        ]);
    }
}
