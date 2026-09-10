<?php

namespace App\Console\Commands;

use App\Services\NubankImportException;
use App\Services\NubankImportMessage;
use App\Services\NubankImportOptions;
use App\Services\NubankImportService;
use Illuminate\Console\Command;

/**
 * Porta de entrada de console para o NubankImportService — a lógica toda vive lá,
 * porque o botão do dashboard roda exatamente a mesma importação.
 */
class ImportPluggyNubank extends Command
{
    protected $signature = 'import:pluggy-nubank
        {--from= : Data inicial (padrão: início do ciclo salarial atual)}
        {--to= : Data final (padrão: hoje)}
        {--replace-investments : Apaga e regrava as caixinhas do período com a taxa real de cada posição}
        {--dry-run : Apenas mostra o que seria gravado}';

    protected $description = 'Importa a movimentação da conta Nubank e as caixinhas direto da API do Pluggy';

    public function handle(NubankImportService $importer): int
    {
        $options = NubankImportOptions::fromInput(
            from: $this->option('from'),
            to: $this->option('to'),
            dryRun: (bool) $this->option('dry-run'),
            replaceInvestments: (bool) $this->option('replace-investments'),
        );

        try {
            // Imprime cada linha assim que acontece, em vez de esperar o fim:
            // a importação leva dezenas de segundos e o log é o retorno visível.
            $result = $importer->import($options, fn (NubankImportMessage $message) => $this->render($message));
        } catch (NubankImportException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info($result->summary());

        return self::SUCCESS;
    }

    private function render(NubankImportMessage $message): void
    {
        match ($message->level) {
            NubankImportMessage::INFO => $this->info($message->text),
            NubankImportMessage::WARN => $this->warn($message->text),
            default => $this->line($message->text),
        };
    }
}
