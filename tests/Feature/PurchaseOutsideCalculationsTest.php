<?php

namespace Tests\Feature;

use App\Models\CreditCard;
use App\Models\CreditCardPurchase;
use App\Services\ProjectionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Compra feita no cartão para outra pessoa, que devolve o valor: fica registrada
 * para consulta, mas não pesa em nenhum cálculo do mês a mês.
 */
class PurchaseOutsideCalculationsTest extends TestCase
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

    public function test_it_stays_out_of_every_bill_while_the_others_stay_in(): void
    {
        $this->purchase('COMPRA PARA TERCEIRO', 5060.00, 10, excluded: true);
        $this->purchase('MERCADO', 300.00, 3);

        $service = app(ProjectionService::class);

        foreach (['2026-12-01', '2027-01-01', '2027-06-01'] as $month) {
            $this->assertNotContains('COMPRA PARA TERCEIRO', $service->cardExpensesForMonth(now()->parse($month))->pluck('description'));
        }

        $this->assertSame(['MERCADO'], $service->cardExpensesForMonth(now()->parse('2026-12-01'))->pluck('description')->all());
    }

    public function test_it_is_still_listed_and_marked(): void
    {
        $this->purchase('COMPRA PARA TERCEIRO', 5060.00, 10, excluded: true);

        $this->unlocked()->get('/purchases')
            ->assertSee('COMPRA PARA TERCEIRO')
            ->assertSee('Fora dos cálculos');
    }

    public function test_the_form_turns_it_on_and_off(): void
    {
        $purchase = $this->purchase('COMPRA PARA TERCEIRO', 5060.00, 10);
        $fields = [
            'credit_card_id' => $this->card->id,
            'description' => 'COMPRA PARA TERCEIRO',
            'total_amount' => 5060,
            'installments' => 10,
            'purchased_at' => '2026-10-23',
        ];

        $this->unlocked()->put("/purchases/{$purchase->id}", $fields + ['excluded_from_projection' => '1']);
        $this->assertTrue($purchase->refresh()->excluded_from_projection);

        $this->unlocked()->put("/purchases/{$purchase->id}", $fields);
        $this->assertFalse($purchase->refresh()->excluded_from_projection);
    }

    public function test_the_card_import_neither_duplicates_it_nor_clears_the_mark(): void
    {
        $purchase = $this->purchase('compra da mae', 5000.00, 10, excluded: true);
        Http::fake([
            '*/auth' => Http::response(['apiKey' => 'chave-falsa']),
            '*/accounts*' => Http::response(['results' => [['id' => 'acc', 'type' => 'CREDIT', 'currencyCode' => 'BRL']]]),
            '*/items/*' => Http::response(['lastUpdatedAt' => '2026-11-06T12:00:00.000Z']),
            '*/v2/transactions*' => Http::response(['results' => [[
                'id' => 'tx-1',
                'date' => '2026-10-23T12:00:00.000Z',
                'createdAt' => '2026-10-23T12:00:00.000Z',
                'amount' => 500.30,
                'description' => 'COMPRA PARA TERCEIRO',
                'creditCardMetadata' => ['purchaseDate' => '2026-10-23T12:00:00.000Z', 'totalInstallments' => 10],
            ]], 'next' => null]),
        ]);

        $this->artisan('import:pluggy-caixa-card', ['--all' => true])->assertSuccessful();

        $this->assertSame(1, CreditCardPurchase::count());
        $this->assertTrue($purchase->refresh()->excluded_from_projection);
        $this->assertEquals(5003.00, $purchase->total_amount);
    }

    private function purchase(string $description, float $total, int $installments, bool $excluded = false): CreditCardPurchase
    {
        return CreditCardPurchase::create([
            'credit_card_id' => $this->card->id,
            'description' => $description,
            'total_amount' => $total,
            'installments' => $installments,
            'purchased_at' => '2026-10-23',
            'excluded_from_projection' => $excluded,
        ]);
    }
}
