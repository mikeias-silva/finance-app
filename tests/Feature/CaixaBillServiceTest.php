<?php

namespace Tests\Feature;

use App\Models\CreditCard;
use App\Services\CaixaBillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * Cobre a parte determinística do service: achar a fatura do mês de vencimento,
 * recortar o período de lançamentos e somar os pagamentos. A projeção de parcelas
 * futuras (unreportedBill/futureInstallments) depende demais do formato do payload
 * da API e ficou de fora — só o caminho do mês sem fatura está coberto.
 */
class CaixaBillServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['pluggy.items.caixa' => 'item-caixa']);
    }

    public function test_it_finds_the_bill_by_its_due_month(): void
    {
        $this->fakePluggy([
            $this->bill(closing: '2026-05-25', due: '2026-06-05', total: 800.00),
            $this->bill(closing: '2026-06-25', due: '2026-07-05', total: 900.00),
        ]);

        $this->assertSame(800.00, $this->service()->totalForMonth(6, 2026));
        $this->assertSame(900.00, $this->service()->totalForMonth(7, 2026));
    }

    public function test_the_cycle_starts_the_day_after_the_previous_bill_closed(): void
    {
        $this->fakePluggy([
            $this->bill(closing: '2026-05-25', due: '2026-06-05', total: 800.00),
            $this->bill(closing: '2026-06-25', due: '2026-07-05', total: 900.00),
        ]);

        $resolved = $this->service()->resolve(7, 2026);

        $this->assertSame('2026-05-26', $resolved['period']['start']);
        $this->assertSame('2026-06-25', $resolved['period']['end']);
        $this->assertSame('api', $resolved['total_amount_source']);
    }

    public function test_the_first_bill_of_the_history_falls_back_to_a_month_long_cycle(): void
    {
        $this->fakePluggy([$this->bill(closing: '2026-06-25', due: '2026-07-05', total: 900.00)]);

        $resolved = $this->service()->resolve(7, 2026);

        // Sem fatura anterior para ancorar, o ciclo é o mês que antecede o fechamento.
        $this->assertSame('2026-05-26', $resolved['period']['start']);
    }

    public function test_payments_report_what_is_paid_and_what_is_left(): void
    {
        $this->fakePluggy([
            $this->bill(closing: '2026-06-25', due: '2026-07-05', total: 800.00, payments: [
                ['amount' => 300.00],
                ['amount' => 200.00],
            ]),
        ]);

        $payments = $this->service()->resolve(7, 2026)['payments'];

        $this->assertSame(500.00, $payments['total_paid']);
        $this->assertSame(300.00, $payments['remaining']);
        $this->assertFalse($payments['is_paid']);
    }

    public function test_a_bill_paid_in_full_is_flagged_as_settled(): void
    {
        $this->fakePluggy([
            $this->bill(closing: '2026-06-25', due: '2026-07-05', total: 800.00, payments: [['amount' => 800.00]]),
        ]);

        $payments = $this->service()->resolve(7, 2026)['payments'];

        $this->assertSame(0.0, $payments['remaining']);
        $this->assertTrue($payments['is_paid']);
    }

    public function test_only_the_transactions_of_the_cycle_are_returned(): void
    {
        $this->fakePluggy(
            bills: [
                $this->bill(closing: '2026-05-25', due: '2026-06-05', total: 800.00),
                $this->bill(closing: '2026-06-25', due: '2026-07-05', total: 900.00),
            ],
            transactions: [
                $this->transaction('2026-06-30', 500.00, 'fatura seguinte'),
                $this->transaction('2026-06-20', 100.00, 'dentro do ciclo'),
                $this->transaction('2026-05-20', 700.00, 'fatura anterior'),
            ],
        );

        $resolved = $this->service()->resolve(7, 2026);

        $this->assertSame(['dentro do ciclo'], array_column($resolved['transactions'], 'description'));
        $this->assertSame(100.00, $resolved['transactions_total']);
    }

    public function test_a_refund_subtracts_from_the_bill_total(): void
    {
        $service = $this->service();

        $this->assertSame(70.00, $service->transactionsTotal([
            ['amount' => 100.00],
            ['amount' => -30.00],
        ]));
    }

    public function test_a_month_without_a_bill_by_design_resolves_to_nothing(): void
    {
        CreditCard::create([
            'name' => 'Caixa - Visa',
            'closing_day' => 22,
            'due_day' => 1,
            'billing_skip_months' => ['2026-07'],
        ]);

        $this->fakePluggy([$this->bill(closing: '2026-06-22', due: '2026-08-01', total: 900.00)]);

        $this->assertNull($this->service()->totalForMonth(7, 2026));
        $this->assertNull($this->service()->tryResolve(7, 2026));
    }

    public function test_it_fails_when_the_item_has_no_credit_card_account(): void
    {
        Http::fake([
            '*/auth' => Http::response(['apiKey' => 'chave-falsa']),
            '*/accounts*' => Http::response(['results' => [
                ['id' => 'acc-1', 'type' => 'BANK', 'currencyCode' => 'BRL'],
            ]]),
        ]);

        $this->expectException(RuntimeException::class);

        $this->service()->resolve(7, 2026);
    }

    private function service(): CaixaBillService
    {
        return app(CaixaBillService::class);
    }

    /** Fatura no formato de GET /bills. */
    private function bill(string $closing, string $due, float $total, array $payments = []): array
    {
        return [
            'id' => 'bill-'.$due,
            'billClosingDate' => $closing,
            'dueDate' => $due,
            'totalAmount' => $total,
            'payments' => $payments,
        ];
    }

    /** Lançamento no formato de GET /v2/transactions, do mais recente ao mais antigo. */
    private function transaction(string $date, float $amount, string $description): array
    {
        return [
            'id' => 'tx-'.$date,
            'date' => $date.'T12:00:00.000Z',
            'amount' => $amount,
            'description' => $description,
        ];
    }

    private function fakePluggy(array $bills, array $transactions = []): void
    {
        Http::fake([
            '*/auth' => Http::response(['apiKey' => 'chave-falsa']),
            '*/accounts*' => Http::response(['results' => [
                ['id' => 'acc-credito', 'type' => 'CREDIT', 'currencyCode' => 'BRL'],
            ]]),
            '*/bills*' => Http::response(['results' => $bills, 'totalPages' => 1]),
            '*/v2/transactions*' => Http::response(['results' => $transactions, 'next' => null]),
        ]);
    }
}
