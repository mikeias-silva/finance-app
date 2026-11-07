<?php

namespace App\Http\Controllers;

use App\Services\DashboardOverview;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Terceira versão da tela inicial: os números do Dashboard 2 num gráfico que se
 * mexe. Todos os ciclos vão para a página de uma vez, então mudar o horizonte ou
 * o mês selecionado responde na hora, sem recarregar.
 */
class Dashboard3Controller extends Controller
{
    private const MIN_MONTHS = 3;

    private const MAX_MONTHS = 24;

    public function index(Request $request, DashboardOverview $overview)
    {
        $months = max(self::MIN_MONTHS, min(self::MAX_MONTHS, (int) $request->input('months', 6)));
        $data = $overview->build(self::MAX_MONTHS);

        return view('dashboard3.index', $data + [
            'months' => $months,
            'minMonths' => self::MIN_MONTHS,
            'maxMonths' => self::MAX_MONTHS,
            'payload' => $this->payload($data, $months),
        ]);
    }

    /** O que o navegador precisa para desenhar a tela, já com datas formatadas. */
    private function payload(array $data, int $months): array
    {
        $series = $this->series($data['cycles']);
        $snapshot = $data['balanceSnapshot'];

        return [
            'months' => $months,
            'series' => array_values($series),
            'anchor' => [
                'value' => round((float) $data['anchor'], 2),
                'checking' => $snapshot?->checking,
                'boxes' => $snapshot?->boxes,
                'readAt' => $snapshot?->synced_at->format('d/m \à\s H:i'),
                'readShort' => $snapshot?->synced_at->format('d/m'),
                'daysOld' => $snapshot ? (int) $snapshot->synced_at->copy()->startOfDay()->diffInDays(today()) : null,
            ],
            'cycles' => $data['cycles']
                ->map(fn (array $cycle, string $key) => $this->cycle($cycle, $key, $series))
                ->values(),
        ];
    }

    /**
     * Uma cor por origem de gasto, fixa em todos os ciclos: um cartão não troca de
     * cor quando o horizonte muda. Duas faturas têm cor própria e as demais somam
     * em "Outras faturas", para o gráfico não virar um arco-íris.
     */
    private function series(Collection $cycles): array
    {
        $cards = $cycles
            ->flatMap(fn (array $cycle) => $cycle['cards']->pluck('name'))
            ->unique()
            ->sort()
            ->values();

        $series = [];

        foreach ($cards->take(2) as $position => $name) {
            $series[$name] = ['id' => $name, 'label' => 'Fatura '.$name, 'slot' => $position === 0 ? 1 : 3];
        }

        if ($cards->count() > 2) {
            $series['others'] = ['id' => 'others', 'label' => 'Outras faturas', 'slot' => 5];
        }

        return $series + [
            'fixed' => ['id' => 'fixed', 'label' => 'Gastos fixos', 'slot' => 2],
            'account' => ['id' => 'account', 'label' => 'Movimentações da conta', 'slot' => 4],
        ];
    }

    private function cycle(array $cycle, string $key, array $series): array
    {
        $date = fn ($value) => Carbon::parse($value)->format('d/m');
        $money = fn (float $value) => round($value, 2);

        $entries = collect();

        if ($cycle['salary'] > 0) {
            $entries->push(['sort' => $cycle['period_start']->toDateString(), 'date' => $cycle['period_start']->format('d/m'), 'description' => 'Salário', 'amount' => $money($cycle['salary'])]);
        }

        foreach ($cycle['recurring_income']->concat($cycle['variable_income']) as $income) {
            $entries->push([
                'sort' => Carbon::parse($income['received_at'])->toDateString(),
                'date' => $date($income['received_at']),
                'description' => $income['description'],
                'amount' => $money((float) $income['amount']),
            ]);
        }

        $groups = $cycle['cards']
            ->map(fn (array $card) => [
                'id' => array_key_exists($card['name'], $series) ? $card['name'] : 'others',
                'title' => 'Fatura '.$card['name'],
                'total' => $money($card['total']),
                'rows' => $card['items']->map(fn (array $item) => [
                    'date' => null,
                    'description' => $item['description'],
                    'tag' => $item['is_recurring'] ? 'recorrente' : $item['current_installment'].'/'.$item['installments'],
                    'recurring' => (bool) $item['is_recurring'],
                    'amount' => $money((float) $item['amount']),
                ])->values(),
            ])
            ->push([
                'id' => 'fixed',
                'title' => 'Gastos fixos',
                'total' => $money($cycle['fixed_expenses']->sum('amount')),
                'rows' => $cycle['fixed_expenses']->sortByDesc('amount')->map(fn (array $item) => [
                    'date' => null,
                    'description' => $item['description'],
                    'tag' => null,
                    'recurring' => false,
                    'amount' => $money((float) $item['amount']),
                ])->values(),
            ])
            ->push([
                'id' => 'account',
                'title' => 'Movimentações da conta',
                'total' => $money($cycle['variable_expenses']->sum('amount')),
                'rows' => $cycle['variable_expenses']->sortBy(fn (array $item) => (string) $item['paid_at'])->map(fn (array $item) => [
                    'date' => $date($item['paid_at']),
                    'description' => $item['description'],
                    'tag' => null,
                    'recurring' => false,
                    'amount' => $money((float) $item['amount']),
                ])->values(),
            ])
            ->filter(fn (array $group) => $group['rows']->isNotEmpty())
            ->values();

        return [
            'key' => $key,
            'title' => ucfirst($cycle['month']->translatedFormat('F \d\e Y')),
            'name' => $cycle['month']->translatedFormat('F'),
            'short' => ucfirst($cycle['month']->translatedFormat('M')),
            'year' => $cycle['month']->format('Y'),
            'start' => $cycle['period_start']->format('d/m'),
            'end' => $cycle['period_end']->format('d/m'),
            'current' => $cycle['is_current'],
            'income' => $money($cycle['income']),
            'expenses' => $money($cycle['expenses']),
            'cards' => $money($cycle['card_expenses']->sum('amount')),
            'result' => $money($cycle['balance']),
            'pending' => $money($cycle['pending']),
            'yield' => $money($cycle['yield']),
            'accumulated' => $money($cycle['accumulated']),
            'committed' => $cycle['committed'] === null ? null : (int) round($cycle['committed'] * 100),
            'entries' => $entries->sortBy('sort')->values(),
            'groups' => $groups,
        ];
    }
}
