<?php

namespace App\Http\Controllers;

use App\Services\BillingPeriod;
use App\Services\PluggyService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AccountController extends Controller
{
    public function index(PluggyService $pluggy): JsonResponse
    {
        $items = array_filter(config('pluggy.items'));

        $accounts = [];

        foreach ($items as $institution => $itemId) {
            foreach ($pluggy->listAccounts($itemId) as $account) {
                $account['institution'] = $institution;
                $accounts[] = $account;
            }
        }

        return response()->json(['results' => $accounts]);
    }

    public function nubankStatement(PluggyService $pluggy): JsonResponse
    {
        $itemId = config('pluggy.items.nubank');

        $account = collect($pluggy->listAccounts($itemId))
            ->firstWhere('type', 'BANK');

        if (! $account) {
            throw new RuntimeException('Conta corrente do Nubank não encontrada.');
        }

        $period = BillingPeriod::for(now());
        $start = $period['start']->copy()->startOfDay();
        $end = $period['end']->copy()->endOfDay();

        $transactions = $pluggy->listTransactions($account['id'], $start, $end);

        return response()->json([
            'period' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
            'balance' => $account['balance'],
            'currency' => $account['currencyCode'],
            'transactions' => $transactions,
        ]);
    }

    /**
     * Extrato da fatura do cartão de crédito da Caixa, filtrando por mês/ano
     * do vencimento (?month=7&year=2026). O período de transações é
     * [fechamento da fatura anterior + 1 dia, fechamento desta fatura].
     * Opcional: ?since=2026-07-22 para listar só lançamentos depois dessa data.
     */
    public function caixaCreditCardStatement(Request $request, PluggyService $pluggy): JsonResponse
    {
        $itemId = config('pluggy.items.caixa');

        $account = collect($pluggy->listAccounts($itemId))
            ->firstWhere('type', 'CREDIT');

        if (! $account) {
            throw new RuntimeException('Cartão de crédito da Caixa não encontrado.');
        }

        $month = (int) $request->query('month', now()->month);
        $year = (int) $request->query('year', now()->year);
        $since = $request->query('since');

        $bills = collect($pluggy->listBills($account['id']))
            ->sortBy(fn (array $bill) => Carbon::parse($bill['billClosingDate'] ?? $bill['dueDate']))
            ->values();

        $billIndex = $bills->search(function (array $bill) use ($month, $year) {
            $dueDate = Carbon::parse($bill['dueDate']);
            return $dueDate->month === $month && $dueDate->year === $year;
        });

        if ($billIndex === false) {
            return $this->openCaixaBill($pluggy, $account, $bills, $month, $year, $since);
        }

        $bill = $bills[$billIndex];
        $end = Carbon::parse($bill['billClosingDate'] ?? $bill['dueDate'])->endOfDay();

        $previousBill = $billIndex > 0 ? $bills[$billIndex - 1] : null;
        $start = $previousBill
            ? Carbon::parse($previousBill['billClosingDate'] ?? $previousBill['dueDate'])->addDay()->startOfDay()
            : $end->copy()->subMonth()->addDay()->startOfDay();

        $transactions = $this->filterSince($pluggy->listTransactions($account['id'], $start, $end), $since);

        return response()->json([
            'bill' => $bill,
            'period' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
            'currency' => $account['currencyCode'],
            'transactions' => $transactions,
        ]);
    }

    /**
     * A fatura do ciclo atual ainda não fechou (não existe em GET /bills).
     * Só tratamos como "fatura em aberto" se o mês/ano pedido for exatamente
     * o ciclo seguinte ao da última fatura fechada; fora isso, é erro real.
     */
    private function openCaixaBill(PluggyService $pluggy, array $account, \Illuminate\Support\Collection $bills, int $month, int $year, ?string $since): JsonResponse
    {
        $lastBill = $bills->last();
        $nextDue = $lastBill ? Carbon::parse($lastBill['dueDate'])->addMonthNoOverflow() : null;

        if (! $nextDue || $nextDue->month !== $month || $nextDue->year !== $year) {
            throw new RuntimeException("Fatura não encontrada para {$month}/{$year}.");
        }

        $start = Carbon::parse($lastBill['billClosingDate'] ?? $lastBill['dueDate'])->addDay()->startOfDay();
        $end = now()->endOfDay();

        $transactions = $this->filterSince($pluggy->listTransactions($account['id'], $start, $end), $since);

        return response()->json([
            'bill' => [
                'id' => null,
                'dueDate' => null,
                'billClosingDate' => null,
                'totalAmount' => $account['balance'],
                'totalAmountCurrencyCode' => $account['currencyCode'],
                'status' => 'OPEN',
            ],
            'period' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
            'currency' => $account['currencyCode'],
            'transactions' => $transactions,
        ]);
    }

    /** Filtra lançamentos com data estritamente posterior a $since (fim do dia), se informado. */
    private function filterSince(array $transactions, ?string $since): array
    {
        if (! $since) {
            return $transactions;
        }

        $sinceDate = Carbon::parse($since)->endOfDay();

        return array_values(array_filter(
            $transactions,
            fn (array $t) => Carbon::parse($t['date'])->greaterThan($sinceDate)
        ));
    }
}
