<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PluggyService
{
    private string $baseUrl;
    private string $clientId;
    private string $clientSecret;

    public function __construct(private readonly SalaryCredit $salaryCredit)
    {
        $this->baseUrl = config('pluggy.base_url');
        $this->clientId = config('pluggy.client_id');
        $this->clientSecret = config('pluggy.client_secret');
    }

    public function apiKey(): string
    {
        return Cache::remember('pluggy_api_key', now()->addMinutes(100), function () {
            $response = Http::post("{$this->baseUrl}/auth", [
                'clientId' => $this->clientId,
                'clientSecret' => $this->clientSecret,
            ]);

            if ($response->failed()) {
                throw new RuntimeException('Falha ao autenticar no Pluggy: '.$response->body());
            }

            return $response->json('apiKey');
        });
    }

    private function client()
    {
        return Http::withHeaders(['X-API-KEY' => $this->apiKey()])->baseUrl($this->baseUrl);
    }

    public function findConnector(string $name): ?array
    {
        $response = $this->client()->get('/connectors', ['name' => $name]);

        if ($response->failed()) {
            throw new RuntimeException('Falha ao buscar conectores no Pluggy: '.$response->body());
        }

        $results = $response->json('results') ?? [];

        foreach ($results as $connector) {
            if (($connector['isOpenFinance'] ?? false) === true) {
                return $connector;
            }
        }

        return $results[0] ?? null;
    }

    public function createItem(int $connectorId): array
    {
        $response = $this->client()->post('/items', [
            'connectorId' => $connectorId,
            'parameters' => [],
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Falha ao criar item no Pluggy: '.$response->body());
        }

        return $response->json();
    }

    public function getItem(string $itemId): array
    {
        $response = $this->client()->get("/items/{$itemId}");

        if ($response->failed()) {
            throw new RuntimeException('Falha ao consultar item no Pluggy: '.$response->body());
        }

        return $response->json();
    }

    public function listAccounts(string $itemId): array
    {
        $response = $this->client()->get('/accounts', ['itemId' => $itemId]);

        if ($response->failed()) {
            throw new RuntimeException('Falha ao listar contas no Pluggy: '.$response->body());
        }

        return $response->json('results') ?? [];
    }

    /**
     * Lista transações de uma conta no período [$from, $to] (inclusive).
     * A API v2 não filtra por data no servidor, então pagina via cursor
     * (resultados vêm em ordem decrescente de data) e para assim que
     * encontrar uma transação anterior a $from.
     */
    public function listTransactions(string $accountId, Carbon $from, Carbon $to): array
    {
        $collected = [];
        $query = ['accountId' => $accountId];
        $nextQuery = null;

        do {
            $response = $nextQuery
                ? $this->client()->get('/v2/transactions?'.$nextQuery)
                : $this->client()->get('/v2/transactions', $query);

            if ($response->failed()) {
                throw new RuntimeException('Falha ao listar transações no Pluggy: '.$response->body());
            }

            $data = $response->json();
            $results = $data['results'] ?? [];

            foreach ($results as $transaction) {
                $date = Carbon::parse($transaction['date']);

                if ($date->greaterThan($to)) {
                    continue;
                }

                if ($date->lessThan($from)) {
                    return $collected;
                }

                $collected[] = $transaction;
            }

            $nextQuery = $data['next'] ?? null;
            if ($nextQuery !== null) {
                $nextQuery = ltrim($nextQuery, '?');
            }
        } while ($nextQuery);

        return $collected;
    }

    /**
     * Saldo real hoje: conta corrente do Nubank + caixinhas. É a âncora do saldo
     * acumulado da projeção. Cacheado por 30 min — o Pluggy sincroniza o item uma
     * vez por dia, então consultar a cada request não traria dado novo.
     */
    public function currentNetWorth(): ?array
    {
        $itemId = config('pluggy.items.nubank');

        if (! $itemId) {
            return null;
        }

        return Cache::remember('pluggy_net_worth', now()->addMinutes(30), function () use ($itemId) {
            $checking = 0.0;
            $checkingAccountId = null;

            foreach ($this->listAccounts($itemId) as $account) {
                if (($account['type'] ?? null) === 'BANK') {
                    $checking += (float) ($account['balance'] ?? 0);
                    $checkingAccountId ??= $account['id'];
                }
            }

            $boxes = 0.0;

            foreach ($this->listInvestments($itemId) as $position) {
                $boxes += (float) ($position['balance'] ?? 0);
            }

            return [
                'checking' => round($checking, 2),
                'boxes' => round($boxes, 2),
                'total' => round($checking + $boxes, 2),
                'salary_credited' => $checkingAccountId === null
                    ? null
                    : $this->salaryCreditedInCurrentCycle($checkingAccountId),
            ];
        });
    }

    /**
     * O salário do ciclo corrente já apareceu no extrato? Anda junto do saldo, na
     * mesma entrada de cache, de propósito: as duas respostas saem da mesma leitura
     * e não podem discordar sobre o mesmo crédito. Lidas em momentos diferentes,
     * abre-se a janela em que o salário não está nem no saldo nem no previsto.
     *
     * null quando não dá para responder — a projeção então decide pelo calendário.
     */
    private function salaryCreditedInCurrentCycle(string $accountId): ?bool
    {
        $cycle = BillingPeriod::current();
        $today = Carbon::today();

        // Ciclo que ainda não começou: o salário só cai no primeiro dia dele.
        if ($today->lt($cycle['start'])) {
            return false;
        }

        $to = $today->lt($cycle['end']) ? $today : $cycle['end'];

        try {
            return $this->salaryCredit->foundIn($this->listTransactions(
                $accountId,
                $cycle['start']->copy()->startOfDay(),
                $to->copy()->endOfDay(),
            ));
        } catch (\Throwable $e) {
            // Falha aqui não pode derrubar o saldo, que é a âncora da projeção.
            report($e);

            return null;
        }
    }

    /** Lista as posições de investimento de um item (no Nubank, cada caixinha é um CDB). */
    public function listInvestments(string $itemId): array
    {
        return $this->paginate('/investments', ['itemId' => $itemId], 'investimentos');
    }

    /** Lista as movimentações (BUY/SELL) de uma posição de investimento. */
    public function listInvestmentTransactions(string $investmentId): array
    {
        return $this->paginate("/investments/{$investmentId}/transactions", [], 'movimentações de investimento');
    }

    /** Lista todas as faturas (bills) de uma conta de cartão de crédito. */
    public function listBills(string $accountId): array
    {
        return $this->paginate('/bills', ['accountId' => $accountId], 'faturas');
    }

    /** Percorre todas as páginas de um endpoint paginado por page/totalPages. */
    private function paginate(string $path, array $query, string $label): array
    {
        $results = [];
        $page = 1;
        $totalPages = 1;

        do {
            $response = $this->client()->get($path, $query + ['page' => $page]);

            if ($response->failed()) {
                throw new RuntimeException("Falha ao listar {$label} no Pluggy: ".$response->body());
            }

            $data = $response->json();
            $results = array_merge($results, $data['results'] ?? []);
            $totalPages = $data['totalPages'] ?? 1;
            $page++;
        } while ($page <= $totalPages);

        return $results;
    }
}
