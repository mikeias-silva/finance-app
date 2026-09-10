<?php

namespace App\Http\Controllers;

use App\Services\BillingPeriod;
use App\Services\CaixaBillService;
use App\Services\PluggyService;
use App\Services\ProjectionService;
use App\Services\RecurringChargeDetector;
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
     * Fatura do cartão de crédito da Caixa por mês/ano do vencimento
     * (?month=8&year=2026). Opcional: ?since=2026-07-22 para listar só
     * lançamentos posteriores a essa data.
     */
    public function caixaCreditCardStatement(Request $request, CaixaBillService $bills): JsonResponse
    {
        return response()->json($bills->resolve(
            (int) $request->query('month', now()->month),
            (int) $request->query('year', now()->year),
            $request->query('since'),
        ));
    }

    /**
     * Fatura da Caixa juntando API e cadastro manual, sem duplicar: o que a API já
     * publicou mais o que só existe no banco — a Caixa demora a publicar, e o
     * lançamento manual antecipa a previsão.
     */
    public function caixaCreditCardMergedStatement(
        Request $request,
        ProjectionService $projection,
        CaixaBillService $bills,
    ): JsonResponse {
        // ProjectionService usa o Carbon do Illuminate, não o do pacote base.
        $month = \Illuminate\Support\Carbon::create(
            (int) $request->query('year', now()->year),
            (int) $request->query('month', now()->month),
            1
        );

        $merged = $projection->mergedCaixaItems($month, $bills);

        if ($merged === null) {
            throw new RuntimeException("Fatura não encontrada para {$month->month}/{$month->year}.");
        }

        return response()->json($merged);
    }

    /**
     * Lançamentos do cartão de crédito da Caixa, sem o recorte por fatura.
     * ?card=4789 filtra um cartão específico (titular ou adicional);
     * ?from=/?to= delimitam o período (padrão: últimos 90 dias).
     */
    public function caixaCreditCardTransactions(Request $request, PluggyService $pluggy, CaixaBillService $bills): JsonResponse
    {
        $account = collect($pluggy->listAccounts(config('pluggy.items.caixa')))
            ->firstWhere('type', 'CREDIT');

        if (! $account) {
            throw new RuntimeException('Cartão de crédito da Caixa não encontrado.');
        }

        $from = Carbon::parse($request->query('from', now()->subDays(90)->toDateString()))->startOfDay();
        $to = Carbon::parse($request->query('to', now()->toDateString()))->endOfDay();
        $card = $request->query('card');

        $transactions = $pluggy->listTransactions($account['id'], $from, $to);
        $summary = $this->summarizeByCard($transactions, $account, $bills);

        if ($card !== null) {
            $transactions = array_values(array_filter(
                $transactions,
                fn (array $t) => ($t['creditCardMetadata']['cardNumber'] ?? null) === $card
            ));
        }

        return response()->json([
            'period' => [
                'start' => $from->toDateString(),
                'end' => $to->toDateString(),
            ],
            'card' => $card,
            'cards' => $summary,
            'currency' => $account['currencyCode'],
            'count' => count($transactions),
            'total' => $bills->transactionsTotal($transactions),
            'transactions' => $transactions,
        ]);
    }

    /**
     * Cobranças recorrentes detectadas nos cartões de crédito (Caixa e Nubank).
     * Só cartões: recorrências de conta corrente, como a aplicação mensal em
     * caixinha, não são gasto de fatura e ficam fora.
     */
    public function creditCardRecurringCharges(PluggyService $pluggy, RecurringChargeDetector $detector): JsonResponse
    {
        $cards = [];
        $total = 0.0;

        foreach (array_filter(config('pluggy.items')) as $institution => $itemId) {
            $account = collect($pluggy->listAccounts($itemId))->firstWhere('type', 'CREDIT');

            if (! $account) {
                continue;
            }

            $charges = $detector->detect(
                $pluggy->listTransactions($account['id'], now()->subYears(2), now())
            );

            $monthly = round(array_sum(array_column($charges, 'amount')), 2);
            $total += $monthly;

            $cards[] = [
                'institution' => $institution,
                'account_id' => $account['id'],
                'monthly_total' => $monthly,
                'charges' => $charges,
            ];
        }

        return response()->json([
            'monthly_total' => round($total, 2),
            'cards' => $cards,
        ]);
    }

    /**
     * Totais por cartão no período. Os lançamentos sem cardNumber são pagamentos
     * de fatura, que não pertencem a nenhum cartão — ficam agrupados sob null.
     */
    private function summarizeByCard(array $transactions, array $account, CaixaBillService $bills): array
    {
        $primary = $account['number'] ?? null;
        $additional = array_column($account['creditData']['additionalCards'] ?? [], 'number');

        // groupBy devolve chaves numéricas como int, então compara sempre como string.
        return collect($transactions)
            ->groupBy(fn (array $t) => $t['creditCardMetadata']['cardNumber'] ?? '')
            ->map(fn ($group, $number) => [
                'number' => (string) $number === '' ? null : (string) $number,
                'is_primary' => (string) $number === (string) $primary,
                'is_additional' => in_array((string) $number, $additional, true),
                'count' => $group->count(),
                'total' => $bills->transactionsTotal($group->all()),
            ])
            ->sortByDesc('count')
            ->values()
            ->all();
    }
}
