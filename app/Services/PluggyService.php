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

    public function __construct()
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

    /** Lista todas as faturas (bills) de uma conta de cartão de crédito. */
    public function listBills(string $accountId): array
    {
        $bills = [];
        $page = 1;
        $totalPages = 1;

        do {
            $response = $this->client()->get('/bills', ['accountId' => $accountId, 'page' => $page]);

            if ($response->failed()) {
                throw new RuntimeException('Falha ao listar faturas no Pluggy: '.$response->body());
            }

            $data = $response->json();
            $bills = array_merge($bills, $data['results'] ?? []);
            $totalPages = $data['totalPages'] ?? 1;
            $page++;
        } while ($page <= $totalPages);

        return $bills;
    }
}
