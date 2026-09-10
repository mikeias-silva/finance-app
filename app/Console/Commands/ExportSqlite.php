<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gera um arquivo SQLite com o schema e os dados do banco atual, para levar a
 * um servidor que roda SQLite. O desenvolvimento aqui usa Postgres, então sem
 * isso o deploy subiria com o banco vazio.
 */
class ExportSqlite extends Command
{
    protected $signature = 'db:export-sqlite
        {--output=database/deploy.sqlite : Caminho do arquivo a gerar}
        {--force : Sobrescreve o arquivo se já existir}';

    protected $description = 'Exporta o banco atual para um arquivo SQLite pronto para deploy';

    /** Tabelas de infraestrutura: o schema vai, os dados não. */
    private const SKIP_DATA = ['migrations', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'];

    public function handle(): int
    {
        $path = base_path($this->option('output'));

        if (file_exists($path) && ! $this->option('force')) {
            $this->error("Arquivo já existe: {$path}. Use --force para sobrescrever.");

            return self::FAILURE;
        }

        @unlink($path);
        touch($path);

        config(['database.connections.sqlite_export' => [
            'driver' => 'sqlite',
            'database' => $path,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]]);

        $this->info("Origem:  ".config('database.default'));
        $this->info("Destino: {$path}");
        $this->newLine();

        $this->line('Criando o schema...');
        Artisan::call('migrate', ['--database' => 'sqlite_export', '--force' => true]);

        $origem = DB::connection();
        $destino = DB::connection('sqlite_export');
        $total = 0;

        foreach ($this->tables($destino) as $table) {
            if (in_array($table, self::SKIP_DATA, true) || ! Schema::hasTable($table)) {
                continue;
            }

            $rows = $origem->table($table)->get()->map(fn ($r) => (array) $r)->all();

            if (! $rows) {
                $this->line(sprintf('  %-32s %s', $table, 'vazia'));
                continue;
            }

            // Em lotes: SQLite tem limite de variáveis por statement.
            foreach (array_chunk($rows, 100) as $chunk) {
                $destino->table($table)->insert($chunk);
            }

            $total += count($rows);
            $this->line(sprintf('  %-32s %d registro(s)', $table, count($rows)));
        }

        $this->newLine();
        $this->info("✅ {$total} registros exportados.");
        $this->line('Tamanho: '.number_format(filesize($path) / 1024, 1).' KB');
        $this->newLine();
        $this->comment('Envie o arquivo como database/database.sqlite no servidor.');

        return self::SUCCESS;
    }

    /** Tabelas criadas pelas migrations, na ordem em que o SQLite as reporta. */
    private function tables($connection): array
    {
        return collect($connection->getSchemaBuilder()->getTables())
            ->pluck('name')
            ->reject(fn ($t) => str_starts_with($t, 'sqlite_'))
            ->values()
            ->all();
    }
}
