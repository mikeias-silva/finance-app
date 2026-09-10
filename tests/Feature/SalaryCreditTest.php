<?php

namespace Tests\Feature;

use App\Models\Salary;
use App\Services\BillingPeriod;
use App\Services\PluggyService;
use App\Services\SalaryCredit;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * A projeção precisa saber se o salário do ciclo já caiu para não somá-lo de novo
 * por cima do saldo real — e precisa saber isso pela mesma leitura de onde sai o
 * saldo, não pelo calendário.
 */
class SalaryCreditTest extends TestCase
{
    use RefreshDatabase;

    private SalaryCredit $credit;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-15'); // dentro do ciclo de junho (29/05 a 29/06)
        config(['pluggy.items.nubank' => 'item-teste']);
        Salary::create(['amount' => 5000.00, 'effective_from' => '2025-01-01']);

        $this->credit = app(SalaryCredit::class);
    }

    public function test_it_recognises_an_own_transfer_on_the_first_day_of_the_cycle(): void
    {
        $this->assertTrue($this->credit->matches($this->transaction(5000.00, '2026-05-29')));
    }

    public function test_a_credit_from_someone_else_is_not_the_salary(): void
    {
        $transaction = $this->transaction(5000.00, '2026-05-29');
        $transaction['paymentData']['payer']['documentNumber']['value'] = '999.999.999-99';

        $this->assertFalse($this->credit->matches($transaction));
    }

    public function test_a_credit_outside_the_first_day_of_the_cycle_is_not_the_salary(): void
    {
        $this->assertFalse($this->credit->matches($this->transaction(5000.00, '2026-06-01')));
    }

    public function test_it_recognises_the_salary_when_the_cycle_opens_on_the_31st(): void
    {
        // 31/08 é segunda: último dia útil de agosto, primeiro dia do ciclo de setembro.
        // Somar um mês a uma data dia 31 estoura para o mês seguinte e desloca o ciclo.
        $this->assertSame('2026-08-31', BillingPeriod::for(Carbon::parse('2026-09-01'))['start']->toDateString());
        $this->assertTrue($this->credit->matches($this->transaction(5000.00, '2026-08-31')));
    }

    public function test_a_value_far_from_the_registered_salary_is_not_the_salary(): void
    {
        // Adiantamento de 2000 no mesmo dia não pode passar por salário.
        $this->assertFalse($this->credit->matches($this->transaction(2000.00, '2026-05-29')));

        // Variação dentro dos 20% (hora extra, desconto) continua sendo.
        $this->assertTrue($this->credit->matches($this->transaction(5400.00, '2026-05-29')));
    }

    public function test_a_debit_is_never_the_salary(): void
    {
        $this->assertFalse($this->credit->matches($this->transaction(-5000.00, '2026-05-29')));
    }

    public function test_the_net_worth_reports_the_salary_as_credited_when_it_is_in_the_statement(): void
    {
        $this->fakePluggy([$this->transaction(5000.00, '2026-05-29')]);

        $this->assertTrue(app(PluggyService::class)->currentNetWorth()['salary_credited']);
    }

    public function test_the_net_worth_reports_the_salary_as_pending_while_it_is_not(): void
    {
        $this->fakePluggy([$this->transaction(-150.00, '2026-06-02', 'Compra no débito|PADARIA')]);

        $this->assertFalse(app(PluggyService::class)->currentNetWorth()['salary_credited']);
    }

    public function test_a_cycle_that_has_not_started_yet_has_no_credit_to_look_for(): void
    {
        // 30/08 é domingo: o ciclo de setembro só abre em 31/08, com o salário.
        Carbon::setTestNow('2026-08-30');
        $this->fakePluggy([]);

        $this->assertSame('2026-08-31', BillingPeriod::current()['start']->toDateString());
        $this->assertFalse(app(PluggyService::class)->currentNetWorth()['salary_credited']);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/v2/transactions'));
    }

    /** O salário chega como transferência do próprio titular vinda de outro banco. */
    private function transaction(float $amount, string $date, string $description = 'Transferência recebida'): array
    {
        return [
            'id' => 'tx-'.md5($date.$amount),
            'date' => Carbon::parse($date)->toIso8601String(),
            'amount' => $amount,
            'description' => $description,
            'paymentData' => [
                'payer' => ['documentNumber' => ['value' => '123.456.789-00']],
                'receiver' => ['documentNumber' => ['value' => '12345678900']],
            ],
        ];
    }

    private function fakePluggy(array $transactions): void
    {
        Http::fake([
            '*/auth' => Http::response(['apiKey' => 'chave-falsa']),
            '*/accounts*' => Http::response([
                'results' => [['id' => 'acc-1', 'type' => 'BANK', 'balance' => 1000.00]],
            ]),
            '*/v2/transactions*' => Http::response(['results' => $transactions, 'next' => null]),
            '*/investments*' => Http::response(['results' => [], 'totalPages' => 1]),
        ]);
    }
}
