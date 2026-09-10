<?php

namespace Tests\Feature;

use App\Models\BalanceSnapshot;
use App\Models\Salary;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Fumaça da tela inicial: ela junta projeção, saldo real e a evidência do salário
 * numa chamada só, e é aqui que a ligação entre eles quebra sem nenhum teste de
 * service acusar. O saldo vem do que a última importação gravou — a tela não fala
 * com o Pluggy.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-31'); // dia em que o salário cai
        Salary::create(['amount' => 5000.00, 'effective_from' => '2025-01-01']);
        Http::fake();
    }

    public function test_the_dashboard_renders_with_the_salary_still_pending(): void
    {
        $this->givenABalanceOf(1000.00, salaryCredited: false);

        $response = $this->unlocked()->get('/');

        $response->assertOk();

        // Sem crédito no extrato, o salário continua previsto: 1000 de saldo + 5000.
        $september = $response->viewData('projection')['2026-09'];

        $this->assertEquals(5000.00, $september['pending']);
        $this->assertEquals(6000.00, $september['accumulated']);
    }

    public function test_the_salary_leaves_the_forecast_once_the_import_saw_it(): void
    {
        $this->givenABalanceOf(6000.00, salaryCredited: true);

        $september = $this->unlocked()->get('/')->viewData('projection')['2026-09'];

        // O salário já está dentro dos 6000 lidos; contá-lo de novo o somaria duas vezes.
        $this->assertEquals(0.0, $september['pending']);
        $this->assertEquals(6000.00, $september['accumulated']);
    }

    public function test_the_dashboard_never_calls_pluggy(): void
    {
        $this->givenABalanceOf(1000.00, salaryCredited: false);

        $this->unlocked()->get('/')->assertOk();

        // A consulta à API acontece quando o usuário manda atualizar, não ao abrir a tela.
        Http::assertNothingSent();
    }

    public function test_it_still_renders_before_the_first_import(): void
    {
        $response = $this->unlocked()->get('/');

        $response->assertOk();
        $this->assertNull($response->viewData('balanceSnapshot'));
        // Sem saldo gravado, a âncora é o total simulado das caixinhas — aqui, zero.
        $this->assertEquals(5000.00, $response->viewData('projection')['2026-09']['accumulated']);
    }

    public function test_the_chart_starts_from_the_balance_of_today(): void
    {
        $this->givenABalanceOf(1000.00, salaryCredited: false);

        $response = $this->unlocked()->get('/');

        // A cadeia parte do saldo real; sem ele no gráfico, a primeira barra já é
        // o fim do ciclo e não se vê de onde a linha saiu.
        $response->assertOk()->assertSee('Saldo em conta mês a mês');
        $this->assertEquals(1000.00, $response->viewData('anchor'));
    }

    private function givenABalanceOf(float $total, ?bool $salaryCredited): void
    {
        BalanceSnapshot::create([
            'checking' => $total,
            'boxes' => 0.0,
            'total' => $total,
            'salary_credited' => $salaryCredited,
            'synced_at' => now(),
        ]);
    }
}
