<?php

namespace Tests\Feature;

use App\Models\CreditCard;
use App\Models\CreditCardPurchase;
use App\Services\ProjectionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * O cadastro manual antecipa a fatura, mas costuma ter valor arredondado e data
 * aproximada. Ao chegar o extrato, o lançamento manual é corrigido em vez de
 * duplicado, e as assinaturas seguem o que foi cobrado de fato.
 */
class ImportPluggyCaixaCardTest extends TestCase
{
    use RefreshDatabase;

    private CreditCard $card;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-06');
        config(['pluggy.items.caixa' => 'item-caixa']);

        $this->card = CreditCard::create(['name' => 'Caixa - Visa', 'closing_day' => 22, 'due_day' => 1]);
    }

    public function test_a_purchase_with_similar_name_value_and_date_is_corrected_not_duplicated(): void
    {
        $manual = $this->purchase('fotop asaas', 179.00, '2026-10-05', installments: 1);
        $this->fakePluggy([$this->line('tx-1', 59.68, 'ASAAS       *FotoP', '2026-10-07', 3)]);

        $this->import();

        $this->assertSame(1, CreditCardPurchase::count());
        $manual->refresh();
        $this->assertSame('fotop asaas', $manual->description);
        $this->assertEquals(179.04, $manual->total_amount);
        $this->assertSame(3, (int) $manual->installments);
        $this->assertSame('2026-10-07', Carbon::parse($manual->purchased_at)->toDateString());
    }

    public function test_it_matches_a_typo_and_an_acquirer_prefix(): void
    {
        $this->purchase('tt burguer', 59.00, '2026-09-03');
        $this->purchase('uber', 16.95, '2026-09-03');
        $this->fakePluggy([
            $this->line('tx-1', 59.80, 'RESERVA TT BURGER - AR', '2026-09-03'),
            $this->line('tx-2', 16.95, 'DL*UberRides', '2026-09-04'),
        ]);

        $this->import();

        $this->assertSame(2, CreditCardPurchase::count());
        $this->assertDatabaseHas('credit_card_purchases', ['description' => 'tt burguer', 'total_amount' => 59.80]);
    }

    public function test_the_same_value_at_another_merchant_is_a_new_purchase(): void
    {
        $this->purchase('barbeiro', 16.20, '2026-08-30');
        $this->fakePluggy([$this->line('tx-1', 16.20, '54.582.961 AGUINALDO D', '2026-09-01')]);

        $this->import();

        $this->assertSame(2, CreditCardPurchase::count());
    }

    public function test_a_similar_name_with_a_distant_value_is_a_new_purchase(): void
    {
        $this->purchase('uber', 16.95, '2026-09-03');
        $this->fakePluggy([$this->line('tx-1', 31.97, 'UBER *TRIP HELP.UBER.COM', '2026-09-03')]);

        $this->import();

        $this->assertSame(2, CreditCardPurchase::count());
    }

    public function test_a_similar_name_with_a_distant_date_is_a_new_purchase(): void
    {
        $this->purchase('uber', 16.95, '2026-08-01');
        $this->fakePluggy([$this->line('tx-1', 16.95, 'DL*UberRides', '2026-09-03')]);

        $this->import();

        $this->assertSame(2, CreditCardPurchase::count());
    }

    public function test_one_manual_purchase_absorbs_only_one_statement_line(): void
    {
        $this->purchase('uber', 16.95, '2026-09-03');
        $this->fakePluggy([
            $this->line('tx-1', 16.95, 'DL*UberRides', '2026-09-04'),
            $this->line('tx-2', 16.50, 'DL*UberRides', '2026-09-04'),
        ]);

        $this->import();

        $this->assertSame(2, CreditCardPurchase::count());
    }

    public function test_dry_run_corrects_nothing(): void
    {
        $this->purchase('mureta leme', 91.00, '2026-09-03');
        $this->fakePluggy([$this->line('tx-1', 91.84, 'MURETA DO LEME', '2026-09-03')]);

        $this->import(['--dry-run' => true]);

        $this->assertDatabaseHas('credit_card_purchases', ['description' => 'mureta leme', 'total_amount' => 91.00]);
    }

    public function test_a_recurring_purchase_is_not_imported_on_another_day_of_the_month(): void
    {
        $this->purchase('Google One', 23.99, '2026-08-20', recurring: true);
        $this->fakePluggy([
            $this->line('tx-1', 23.99, 'Google One', '2026-09-11'),
            $this->line('tx-2', 23.99, 'Google One', '2026-10-09'),
        ]);

        $this->import();

        $this->assertSame(1, CreditCardPurchase::count());
    }

    public function test_another_charge_from_the_same_company_is_not_the_recurring_one(): void
    {
        $this->purchase('Google One', 23.99, '2026-08-20', recurring: true);
        $this->fakePluggy([$this->line('tx-1', 53.90, 'GOOGLE YouTube Premium', '2026-09-11')]);

        $this->import();

        $this->assertDatabaseHas('credit_card_purchases', ['description' => 'GOOGLE YouTube Premium']);
    }

    public function test_only_the_difference_of_a_recurring_charge_is_recorded_in_the_month(): void
    {
        $this->purchase('Anthropic Claude', 119.93, '2026-10-25', recurring: true);
        $this->fakePluggy([
            $this->line('tx-1', 120.00, 'ANTHROPIC* CLAUDE SUB', '2026-11-02'),
            $this->line('tx-2', 4.20, 'ANTHROPIC* CLAUDE SUB', '2026-11-02'),
        ]);

        $this->import();

        $this->assertSame(2, CreditCardPurchase::count());
        $this->assertDatabaseHas('credit_card_purchases', [
            'description' => 'Ajuste Anthropic Claude',
            'total_amount' => 4.27,
            'installments' => 1,
            'is_recurring' => false,
        ]);
    }

    public function test_the_lines_of_the_charge_summing_the_registered_value_create_no_adjustment(): void
    {
        $this->purchase('Anthropic Claude', 119.93, '2026-10-25', recurring: true);
        $this->fakePluggy([
            $this->line('tx-1', 115.88, 'ANTHROPIC* CLAUDE SUB', '2026-10-25'),
            $this->line('tx-2', 4.05, 'ANTHROPIC* CLAUDE SUB', '2026-10-25'),
        ]);

        $this->import();

        $this->assertSame(1, CreditCardPurchase::count());
    }

    public function test_running_again_keeps_a_single_adjustment_and_follows_the_new_value(): void
    {
        $this->purchase('Anthropic Claude', 119.93, '2026-10-25', recurring: true);
        $this->fakePluggy([$this->line('tx-1', 122.00, 'ANTHROPIC* CLAUDE SUB', '2026-11-02')]);
        $this->import();

        Http::swap(new Factory);
        $this->fakePluggy([$this->line('tx-1b', 125.00, 'ANTHROPIC* CLAUDE SUB', '2026-11-02')]);
        $this->import();

        $this->assertSame(1, CreditCardPurchase::where('description', 'Ajuste Anthropic Claude')->count());
        $this->assertDatabaseHas('credit_card_purchases', ['description' => 'Ajuste Anthropic Claude', 'total_amount' => 5.07]);
    }

    public function test_a_pending_and_a_posted_copy_of_a_charge_count_once(): void
    {
        $this->purchase('Anthropic Claude', 119.93, '2026-10-25', recurring: true);
        $this->fakePluggy([
            $this->line('tx-1', 119.93, 'ANTHROPIC* CLAUDE SUB', '2026-11-02'),
            $this->line('tx-2', 119.93, 'ANTHROPIC* CLAUDE SUB', '2026-11-02'),
        ]);

        $this->import();

        $this->assertSame(1, CreditCardPurchase::count());
    }

    public function test_charges_before_the_recurring_started_are_not_adjusted(): void
    {
        $this->purchase('Anthropic Claude', 119.93, '2026-10-25', recurring: true);
        $this->fakePluggy([$this->line('tx-1', 90.00, 'ANTHROPIC* CLAUDE SUB', '2026-08-25')]);

        $this->import();

        $this->assertSame(1, CreditCardPurchase::count());
    }

    public function test_purchased_from_leaves_older_purchases_out(): void
    {
        $this->fakePluggy([
            $this->line('tx-1', 10.00, 'LOJA ANTIGA', '2026-08-15'),
            $this->line('tx-2', 20.00, 'LOJA NOVA', '2026-09-15'),
        ]);

        $this->import(['--purchased-from' => '2026-09-01']);

        $this->assertDatabaseMissing('credit_card_purchases', ['description' => 'LOJA ANTIGA']);
        $this->assertDatabaseHas('credit_card_purchases', ['description' => 'LOJA NOVA']);
    }

    public function test_purchases_before_the_sync_start_are_left_alone_by_default(): void
    {
        $this->purchase('hotel RJ', 2440.64, '2026-07-26', installments: 4);
        $this->fakePluggy([
            $this->line('tx-1', 610.17, 'EBN         *NOMAD', '2026-07-26', 4),
            $this->line('tx-2', 57.80, 'VIAREGGIO', '2026-09-15'),
            $this->line('tx-3', 254.74, 'OUTBACK STEAKHOUSE', '2026-10-16'),
        ]);

        $this->artisan('import:pluggy-caixa-card', ['--all' => true])->assertSuccessful();

        $this->assertDatabaseHas('credit_card_purchases', ['description' => 'hotel RJ', 'total_amount' => 2440.64]);
        $this->assertDatabaseMissing('credit_card_purchases', ['description' => 'VIAREGGIO']);
        $this->assertDatabaseHas('credit_card_purchases', ['description' => 'OUTBACK STEAKHOUSE']);
    }

    public function test_a_manual_purchase_with_another_name_on_the_same_installments_is_corrected(): void
    {
        $manual = $this->purchase('passagem', 300.00, '2026-10-20', installments: 3);
        $this->fakePluggy([$this->line('tx-1', 100.50, 'LATAM AIRLINES', '2026-10-21', 3)]);

        $this->import();

        $this->assertSame(1, CreditCardPurchase::count());
        $this->assertEquals(301.50, $manual->refresh()->total_amount);
    }

    public function test_a_few_reais_typed_wrong_do_not_duplicate(): void
    {
        $this->purchase('outback', 250.00, '2026-10-16');
        $this->fakePluggy([$this->line('tx-1', 254.74, 'OUTBACK STEAKHOUSE', '2026-10-16')]);

        $this->import();

        $this->assertSame(1, CreditCardPurchase::count());
        $this->assertDatabaseHas('credit_card_purchases', ['description' => 'outback', 'total_amount' => 254.74]);
    }

    public function test_an_unnamed_purchase_on_the_same_day_and_value_is_the_same_purchase(): void
    {
        $this->purchase('jantar', 80.00, '2026-10-16');
        $this->fakePluggy([$this->line('tx-1', 80.40, 'RESTAURANTE XYZ', '2026-10-16')]);

        $this->import();

        $this->assertSame(1, CreditCardPurchase::count());
    }

    public function test_importing_twice_changes_nothing_the_second_time(): void
    {
        $this->purchase('uber', 16.00, '2026-10-03');
        $this->fakePluggy([
            $this->line('tx-1', 16.95, 'DL*UberRides', '2026-10-04'),
            $this->line('tx-2', 57.80, 'VIAREGGIO', '2026-10-15'),
        ]);

        $this->import();
        $this->import();

        $this->assertSame(2, CreditCardPurchase::count());
    }

    public function test_the_default_window_reaches_lines_from_a_failed_day(): void
    {
        $this->fakePluggy([$this->line('tx-1', 20.00, 'LOJA', '2026-11-02')]);

        $this->artisan('import:pluggy-caixa-card')->assertSuccessful();

        $this->assertDatabaseHas('credit_card_purchases', ['description' => 'LOJA']);
    }

    public function test_a_purchase_on_the_closing_day_falls_on_the_next_bill(): void
    {
        $this->purchase('mercado livre', 34.99, '2026-09-22');
        $this->purchase('livraria', 20.00, '2026-09-21');

        $projection = app(ProjectionService::class);

        $this->assertSame(['livraria'], $projection->cardExpensesForMonth(now()->setDate(2026, 10, 1))->pluck('description')->all());
        $this->assertSame(['mercado livre'], $projection->cardExpensesForMonth(now()->setDate(2026, 11, 1))->pluck('description')->all());
    }

    private function import(array $options = []): void
    {
        $this->artisan('import:pluggy-caixa-card', $options + ['--all' => true, '--purchased-from' => '2025-01-01'])->assertSuccessful();
    }

    private function purchase(
        string $description,
        float $total,
        string $date,
        int $installments = 1,
        bool $recurring = false,
    ): CreditCardPurchase {
        $purchase = CreditCardPurchase::create([
            'credit_card_id' => $this->card->id,
            'description' => $description,
            'total_amount' => $total,
            'installments' => $installments,
            'purchased_at' => $date,
        ]);
        $purchase->forceFill(['is_recurring' => $recurring])->save();

        return $purchase;
    }

    private function line(string $id, float $amount, string $description, string $date, int $installments = 1): array
    {
        return [
            'id' => $id,
            'date' => $date.'T12:00:00.000Z',
            'createdAt' => $date.'T12:00:00.000Z',
            'amount' => $amount,
            'description' => $description,
            'creditCardMetadata' => ['purchaseDate' => $date.'T12:00:00.000Z', 'totalInstallments' => $installments],
        ];
    }

    private function fakePluggy(array $transactions): void
    {
        Http::fake([
            '*/auth' => Http::response(['apiKey' => 'chave-falsa']),
            '*/accounts*' => Http::response(['results' => [
                ['id' => 'acc-credito', 'type' => 'CREDIT', 'currencyCode' => 'BRL'],
            ]]),
            '*/items/*' => Http::response(['lastUpdatedAt' => '2026-11-06T12:00:00.000Z']),
            '*/v2/transactions*' => Http::response(['results' => $transactions, 'next' => null]),
        ]);
    }
}
