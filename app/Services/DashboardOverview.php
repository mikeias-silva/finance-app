<?php

namespace App\Services;

use App\Models\BalanceSnapshot;
use App\Models\InvestmentBox;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Os números das telas iniciais novas. Vive fora dos controllers porque as versões
 * convivem lado a lado até uma ser escolhida e precisam mostrar exatamente o mesmo.
 */
class DashboardOverview
{
    public function __construct(
        private readonly ProjectionService $projection,
        private readonly InvestmentService $investments,
    ) {}

    public function build(int $months): array
    {
        // O ciclo de hoje vem da fonte da verdade: o penúltimo dia útil ainda
        // pertence ao ciclo que termina, o salário do próximo só cai amanhã.
        $from = Carbon::instance(BillingPeriod::current()['end'])->startOfMonth();
        $to = $from->copy()->addMonths($months - 1);

        // O saldo real vem da última importação; a tela não consulta o Pluggy.
        $balanceSnapshot = BalanceSnapshot::latestSnapshot();

        $investmentData = InvestmentBox::with('transactions')->where('is_active', true)->get()
            ->map(fn (InvestmentBox $box) => [
                'box' => $box,
                'balance' => $this->investments->currentBalance($box),
                'yield' => $this->investments->calculateYield($box),
                'total' => $this->investments->totalWithYield($box),
            ]);

        // Antes da primeira importação, a âncora é o total simulado das caixinhas.
        $anchor = $balanceSnapshot?->total ?? $investmentData->sum('total');

        $cycles = $this->projection
            ->accumulate($this->projection->project($from, $to), $anchor, $balanceSnapshot?->salaryCreditedInCurrentCycle())
            ->map(fn (array $cycle) => $this->summarize($cycle));

        // O fundo do poço é o menor saldo em conta da cadeia, não o pior resultado
        // de ciclo: um mês ruim depois de outro afunda mais que o pior dos dois.
        $lowest = $cycles->sortBy('accumulated')->first();
        $last = $cycles->last();

        return [
            'cycles' => $cycles,
            'anchor' => $anchor,
            'balanceSnapshot' => $balanceSnapshot,
            'lowest' => ['month' => $lowest['month'], 'value' => $lowest['accumulated']],
            'horizonEnd' => ['month' => $last['month'], 'value' => $last['accumulated']],
            'investmentData' => $investmentData,
            'totalInvested' => $investmentData->sum('balance'),
            'totalYield' => $investmentData->sum('yield'),
            'totalWithYield' => $investmentData->sum('total'),
        ];
    }

    /** Os totais que a tela mostra de cada ciclo, calculados aqui e não na view. */
    private function summarize(array $cycle): array
    {
        $income = $cycle['salary']
            + $cycle['recurring_income']->sum('amount')
            + $cycle['variable_income']->sum('amount');

        $expenses = $cycle['card_expenses']->sum('amount')
            + $cycle['fixed_expenses']->sum('amount')
            + $cycle['variable_expenses']->sum('amount');

        return $cycle + [
            'income' => $income,
            'expenses' => $expenses,
            // Sem receita não há fração a comprometer: fica em branco, não infinito.
            'committed' => $income > 0 ? $expenses / $income : null,
            // A mesma regra do accumulate(): só o ciclo que contém hoje está em curso.
            'is_current' => Carbon::today()->betweenIncluded($cycle['period_start'], $cycle['period_end']),
            'cards' => $cycle['card_expenses']
                ->groupBy('card')
                ->map(fn (Collection $items, string $name) => [
                    'name' => $name,
                    'color' => $items->first()['color'] ?? null,
                    'total' => $items->sum('amount'),
                    'items' => $items->sortByDesc('amount')->values(),
                ])
                ->sortByDesc('total')
                ->values(),
        ];
    }
}
