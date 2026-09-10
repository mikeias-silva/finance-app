<?php

namespace Tests\Feature;

use App\Models\InvestmentBox;
use App\Models\InvestmentTransaction;
use App\Models\VariableExpense;
use App\Services\BillingPeriod;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** O botão do dashboard: a rota que dispara a mesma importação do comando. */
class NubankImportTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'senha-de-teste';

    private string $date;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'simple_auth.hash' => Hash::make(self::PASSWORD),
            'pluggy.items.nubank' => 'item-teste',
        ]);

        $this->date = BillingPeriod::for(now())['start']->copy()->addDay()->toDateString();

        $this->fakePluggy();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->post('/imports/nubank')->assertRedirect('/login');

        $this->assertSame(0, VariableExpense::count());
    }

    public function test_it_imports_and_returns_the_summary_with_the_result(): void
    {
        $this->unlock();

        $this->from('/')
            ->post('/imports/nubank')
            ->assertRedirect('/')
            ->assertSessionHas('success')
            ->assertSessionHas('import_result');

        $this->assertDatabaseHas('variable_expenses', [
            'identifier' => 'pluggy:tx-1',
            'amount' => 150.75,
        ]);
    }

    public function test_the_result_in_the_session_is_plain_data_the_view_can_render(): void
    {
        $this->unlock();

        $result = $this->from('/')->post('/imports/nubank')->getSession()->get('import_result');

        // A view lê arrays, não objetos: a sessão precisa sobreviver à serialização.
        $this->assertIsArray($result);
        $this->assertSame(1, $result['created']);
        $this->assertSame([
            'type' => 'expense',
            'date' => $this->date,
            'amount' => 150.75,
            'description' => 'Compra no débito|PADARIA',
        ], $result['entries'][0]);
    }

    public function test_dry_run_reports_without_writing(): void
    {
        $this->unlock();

        $this->from('/')
            ->post('/imports/nubank', ['dry_run' => '1'])
            ->assertSessionHas('success', fn (string $message) => str_contains($message, 'Nada foi alterado.'));

        $this->assertSame(0, VariableExpense::count());
    }

    public function test_replacing_the_boxes_without_confirming_is_rejected(): void
    {
        $this->unlock();

        $box = InvestmentBox::create(['name' => 'Reserva', 'rate_percentage' => 100, 'is_active' => true]);
        InvestmentTransaction::create([
            'investment_box_id' => $box->id,
            'type' => 'deposit',
            'amount' => 500.00,
            'transacted_at' => $this->date,
        ]);

        $this->from('/')
            ->post('/imports/nubank', ['replace_investments' => '1'])
            ->assertRedirect('/')
            ->assertSessionHasErrors('confirm_replace');

        $this->assertSame(1, InvestmentTransaction::count());
    }

    public function test_replacing_the_boxes_with_confirmation_goes_through(): void
    {
        $this->unlock();

        $this->from('/')
            ->post('/imports/nubank', ['replace_investments' => '1', 'confirm_replace' => '1'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');
    }

    public function test_an_end_date_before_the_start_date_is_rejected(): void
    {
        $this->unlock();

        $this->from('/')
            ->post('/imports/nubank', ['from' => '2026-08-10', 'to' => '2026-08-01'])
            ->assertSessionHasErrors('to');

        $this->assertSame(0, VariableExpense::count());
    }

    public function test_a_missing_pluggy_item_becomes_a_flash_error(): void
    {
        $this->unlock();

        config(['pluggy.items.nubank' => null]);

        $this->from('/')
            ->post('/imports/nubank')
            ->assertRedirect('/')
            ->assertSessionHas('error');
    }

    public function test_a_second_run_is_blocked_while_one_is_in_progress(): void
    {
        $this->unlock();

        Cache::lock('import:pluggy-nubank', 300)->get();

        $this->from('/')
            ->post('/imports/nubank')
            ->assertSessionHas('error', 'Uma importação já está em andamento. Aguarde ela terminar.');

        $this->assertSame(0, VariableExpense::count());
    }

    public function test_it_clears_the_cached_balance_so_the_dashboard_reloads_fresh(): void
    {
        $this->unlock();

        Cache::put('pluggy_net_worth', ['total' => 1.0], now()->addMinutes(30));

        $this->from('/')->post('/imports/nubank');

        $this->assertNull(Cache::get('pluggy_net_worth'));
    }

    public function test_the_dashboard_shows_the_import_button(): void
    {
        $this->unlock();

        $this->get('/')
            ->assertOk()
            ->assertSee('Atualizar Nubank')
            ->assertSee(route('imports.nubank'), false);
    }

    public function test_the_result_is_rendered_as_a_table_on_the_dashboard(): void
    {
        $this->unlock();

        $this->from('/')->post('/imports/nubank');

        $this->get('/')
            ->assertOk()
            ->assertSee('Importação do Nubank')
            ->assertSee('Lançamentos gravados')
            ->assertSee('Compra no débito|PADARIA')
            ->assertSee('R$ 150,75');
    }

    public function test_a_dry_run_is_labelled_as_a_simulation_on_the_dashboard(): void
    {
        $this->unlock();

        $this->from('/')->post('/imports/nubank', ['dry_run' => '1']);

        $this->get('/')
            ->assertOk()
            ->assertSee('Simulação')
            ->assertSee('Lançamentos que entrariam')
            ->assertSee('Nada foi gravado');
    }

    private function unlock(): void
    {
        $this->post('/login', ['password' => self::PASSWORD]);
    }

    private function fakePluggy(): void
    {
        Http::fake([
            '*/auth' => Http::response(['apiKey' => 'chave-falsa']),
            '*/accounts*' => Http::response([
                'results' => [['id' => 'acc-1', 'type' => 'BANK', 'balance' => 1000.00, 'currencyCode' => 'BRL']],
            ]),
            '*/v2/transactions*' => Http::response([
                'results' => [[
                    'id' => 'tx-1',
                    'date' => Carbon::parse($this->date)->toIso8601String(),
                    'amount' => -150.75,
                    'description' => 'Compra no débito|PADARIA',
                ]],
                'next' => null,
            ]),
            '*/investments*' => Http::response(['results' => [], 'totalPages' => 1]),
        ]);
    }
}
