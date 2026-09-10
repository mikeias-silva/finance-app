<?php

namespace Tests\Feature;

use App\Console\Commands\ImportPluggyNubank;
use App\Models\VariableExpense;
use App\Services\BillingPeriod;
use App\Services\NubankImportOptions;
use App\Services\NubankImportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\TestCase;

/**
 * O comando virou um invólucro sobre o NubankImportService. Estes testes existem
 * para garantir que ele continua imprimindo o log e respeitando as flags — e que
 * o que sai no console é exatamente o que a web recebe.
 */
class ImportPluggyNubankCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $date;

    protected function setUp(): void
    {
        parent::setUp();

        config(['pluggy.items.nubank' => 'item-teste']);

        $this->date = BillingPeriod::for(now())['start']->copy()->addDay()->toDateString();

        $this->fakePluggy();
    }

    public function test_it_imports_and_prints_the_summary(): void
    {
        $tester = $this->runCommand();

        $this->assertSame(SymfonyCommand::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('1 lançamentos gravados', $tester->getDisplay());
        $this->assertDatabaseHas('variable_expenses', ['identifier' => 'pluggy:tx-1']);
    }

    public function test_dry_run_writes_nothing(): void
    {
        $tester = $this->runCommand(['--dry-run' => true]);

        $this->assertSame(SymfonyCommand::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('Modo dry-run: nada será gravado.', $tester->getDisplay());
        $this->assertSame(0, VariableExpense::count());
    }

    public function test_a_missing_pluggy_item_fails_without_an_exception(): void
    {
        config(['pluggy.items.nubank' => null]);

        $tester = $this->runCommand();

        $this->assertSame(SymfonyCommand::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('PLUGGY_ITEM_ID_NUBANK não configurado', $tester->getDisplay());
    }

    public function test_the_console_prints_the_same_lines_the_web_receives(): void
    {
        // Paridade CLI × web: as duas portas de entrada chamam o mesmo service,
        // e é este teste que prova que nenhuma linha se perde no caminho.
        $display = $this->runCommand(['--dry-run' => true])->getDisplay();

        $result = app(NubankImportService::class)->import(new NubankImportOptions(dryRun: true));

        foreach ($result->messages as $message) {
            if ($message->text === '') {
                continue;
            }

            $this->assertStringContainsString($message->text, $display);
        }

        $this->assertStringContainsString($result->summary(), $display);
    }

    private function runCommand(array $arguments = []): CommandTester
    {
        $command = $this->app->make(ImportPluggyNubank::class);
        $command->setLaravel($this->app);
        $command->setApplication(new Application);

        $tester = new CommandTester($command);
        $tester->execute($arguments);

        return $tester;
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
