<?php

namespace App\Http\Controllers;

use App\Services\NubankImportOptions;
use App\Services\NubankImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Porta de entrada web para o NubankImportService, disparada pelo botão do
 * dashboard. Roda a mesma importação do comando `import:pluggy-nubank`.
 */
class NubankImportController extends Controller
{
    /** Segundos de trava e de execução: a importação pagina a API do Pluggy. */
    private const TIMEOUT = 300;

    public function store(Request $request, NubankImportService $importer): RedirectResponse
    {
        $data = $this->validated($request);

        // Sem a trava, um duplo clique roda duas importações em paralelo: as duas
        // leem o banco antes de qualquer gravação, e a reconciliação por (data, valor)
        // conclui nas duas que o lançamento falta.
        $lock = Cache::lock('import:pluggy-nubank', self::TIMEOUT);

        if (! $lock->get()) {
            return back()->with('error', 'Uma importação já está em andamento. Aguarde ela terminar.');
        }

        // php.prod.ini limita a 60s, curto demais para as chamadas paginadas ao
        // Pluggy. O nginx já concede 300s (fastcgi_read_timeout).
        set_time_limit(self::TIMEOUT);

        try {
            $result = $importer->import(new NubankImportOptions(
                from: $data['from'] ?? null,
                to: $data['to'] ?? null,
                dryRun: $request->boolean('dry_run'),
                replaceInvestments: $request->boolean('replace_investments'),
            ));
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'Falha na importação: '.$e->getMessage());
        } finally {
            $lock->release();
        }

        // O saldo real ancora o acumulado da projeção e fica 30 min em cache
        // (PluggyService::currentNetWorth()); sem isto o dashboard recarrega
        // mostrando o saldo de antes da importação.
        Cache::forget('pluggy_net_worth');

        return back()
            ->with('success', $result->summary())
            ->with('import_result', $result->toArray());
    }

    /**
     * Devolve os dados validados com as datas já em Carbon.
     *
     * @return array{from?: \Carbon\Carbon, to?: \Carbon\Carbon}
     */
    private function validated(Request $request): array
    {
        $rules = [
            'from' => ['nullable', 'date'],
            // A regra só entra com `from` preenchido: sem ele o Laravel tentaria
            // ler "from" como se fosse uma data literal.
            'to' => $request->filled('from')
                ? ['nullable', 'date', 'after_or_equal:from']
                : ['nullable', 'date'],
            'dry_run' => ['nullable', 'boolean'],
            'replace_investments' => ['nullable', 'boolean'],
            // Regrava caixinhas apaga TODAS as investment_transactions, então
            // exige uma confirmação explícita além do próprio checkbox.
            'confirm_replace' => ['accepted_if:replace_investments,1'],
        ];

        $data = $request->validate($rules, [
            'from.date' => 'A data inicial é inválida.',
            'to.date' => 'A data final é inválida.',
            'to.after_or_equal' => 'A data final não pode ser anterior à data inicial.',
            'confirm_replace.accepted_if' => 'Confirme a regravação das caixinhas para continuar.',
        ]);

        return array_filter([
            'from' => filled($data['from'] ?? null) ? \Carbon\Carbon::parse($data['from'])->startOfDay() : null,
            'to' => filled($data['to'] ?? null) ? \Carbon\Carbon::parse($data['to'])->endOfDay() : null,
        ]);
    }
}
