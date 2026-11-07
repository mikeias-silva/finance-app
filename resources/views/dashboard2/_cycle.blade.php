{{--
    Um ciclo por inteiro: os números dele, o que entra e o que sai. Cada grupo de
    saída mostra quanto da receita leva — é o "quanto já está comprometido".
--}}
@php
    $date = fn ($value) => \Illuminate\Support\Carbon::parse($value);

    $entries = collect();
    if ($cycle['salary'] > 0) {
        $entries->push(['date' => $cycle['period_start'], 'description' => 'Salário', 'amount' => $cycle['salary']]);
    }
    foreach ($cycle['recurring_income'] as $income) {
        $entries->push(['date' => $date($income['received_at']), 'description' => $income['description'], 'amount' => $income['amount']]);
    }
    foreach ($cycle['variable_income'] as $income) {
        $entries->push(['date' => $date($income['received_at']), 'description' => $income['description'], 'amount' => $income['amount']]);
    }
    $entries = $entries->sortBy(fn (array $entry) => $entry['date']->format('Y-m-d'))->values();

    $groups = $cycle['cards']
        ->map(fn (array $card) => [
            'title' => 'Fatura '.$card['name'],
            'color' => $card['color'],
            'icon' => null,
            'total' => $card['total'],
            'rows' => $card['items']->map(fn (array $item) => [
                'date' => null,
                'description' => $item['description'],
                'tag' => $item['is_recurring'] ? 'recorrente' : $item['current_installment'].'/'.$item['installments'],
                'recurring' => (bool) $item['is_recurring'],
                'amount' => $item['amount'],
            ]),
        ])
        ->push([
            'title' => 'Gastos fixos',
            'color' => null,
            'icon' => 'bi-receipt',
            'total' => $cycle['fixed_expenses']->sum('amount'),
            'rows' => $cycle['fixed_expenses']->sortByDesc('amount')->map(fn (array $item) => [
                'date' => null,
                'description' => $item['description'],
                'tag' => null,
                'recurring' => false,
                'amount' => $item['amount'],
            ])->values(),
        ])
        ->push([
            'title' => 'Movimentações da conta',
            'color' => null,
            'icon' => 'bi-arrow-left-right',
            'total' => $cycle['variable_expenses']->sum('amount'),
            'rows' => $cycle['variable_expenses']->sortBy(fn (array $item) => (string) $item['paid_at'])->map(fn (array $item) => [
                'date' => $date($item['paid_at']),
                'description' => $item['description'],
                'tag' => null,
                'recurring' => false,
                'amount' => $item['amount'],
            ])->values(),
        ])
        ->filter(fn (array $group) => $group['rows']->isNotEmpty());

    $share = function (float $total) use ($cycle): ?string {
        if ($cycle['income'] <= 0) {
            return null;
        }
        $percent = $total / $cycle['income'] * 100;

        return $percent > 0 && $percent < 1 ? '<1%' : round($percent).'%';
    };
    $committed = $cycle['committed'] === null ? null : (int) round($cycle['committed'] * 100);
@endphp

<div class="d2-cycle-head">
    <h3 class="d2-cycle-title">{{ ucfirst($cycle['month']->translatedFormat('F \d\e Y')) }}</h3>
    <span class="muted">Ciclo de {{ $cycle['period_start']->format('d/m') }} a {{ $cycle['period_end']->format('d/m') }}</span>
    @if($cycle['is_current'])
        <span class="d2-pill">atual</span>
    @endif
</div>

<dl class="d2-facts">
    <div>
        <dt>Receitas</dt>
        <dd><x-money :value="$cycle['income']" /></dd>
    </div>
    <div>
        <dt>Despesas</dt>
        <dd><x-money :value="$cycle['expenses']" /></dd>
        <dd class="d2-fact-note">Faturas <x-money :value="$cycle['card_expenses']->sum('amount')" /></dd>
    </div>
    <div>
        <dt>Resultado do ciclo</dt>
        <dd><x-money :value="$cycle['balance']" signed tone /></dd>
        <dd class="d2-fact-note">{{ $committed === null ? 'Ciclo sem receita' : $committed.'% da receita comprometida' }}</dd>
    </div>
    <div>
        <dt>Saldo em conta no fim</dt>
        <dd><x-money :value="$cycle['accumulated']" :class="$cycle['accumulated'] < 0 ? 'negative' : ''" /></dd>
        <dd class="d2-fact-note">
            @if($isFirst)
                Falta acontecer <x-money :value="$cycle['pending']" signed />
            @else
                Em {{ $cycle['period_end']->format('d/m') }}, antes do salário
            @endif
        </dd>
    </div>
</dl>

<div class="row g-4">
    <div class="col-lg-5">
        <h4 class="d2-list-title"><i class="bi bi-arrow-up me-1 positive" aria-hidden="true"></i>Entradas</h4>
        @forelse($entries as $entry)
            <div class="d2-row">
                <span class="d2-row-date num">{{ $entry['date']->format('d/m') }}</span>
                <span class="d2-row-desc">{{ $entry['description'] }}</span>
                <x-money class="num" :value="$entry['amount']" />
            </div>
        @empty
            <p class="muted small mb-0">Nenhuma entrada neste ciclo.</p>
        @endforelse
        <div class="d2-row d2-row-total">
            <span class="d2-row-desc">Total de entradas</span>
            <x-money class="num" :value="$cycle['income']" />
        </div>
    </div>

    <div class="col-lg-7">
        <h4 class="d2-list-title"><i class="bi bi-arrow-down me-1 negative" aria-hidden="true"></i>Saídas</h4>
        @forelse($groups as $group)
            @php $groupShare = $share($group['total']); @endphp
            <details class="d2-group">
                <summary>
                    <span class="d2-group-name">
                        <i class="bi bi-chevron-right d2-chevron" aria-hidden="true"></i>
                        @if($group['color'])
                            <span class="d2-dot" style="background: {{ $group['color'] }};" aria-hidden="true"></span>
                        @else
                            <i class="bi {{ $group['icon'] }} muted" aria-hidden="true"></i>
                        @endif
                        <span>{{ $group['title'] }}</span>
                        <span class="muted d2-group-count">{{ $group['rows']->count() }} {{ $group['rows']->count() === 1 ? 'item' : 'itens' }}</span>
                    </span>
                    <span class="d2-share">
                        @if($groupShare !== null)
                            <span class="d2-meter d2-meter-sm" aria-hidden="true">
                                <span style="width: {{ min(100, $group['total'] / $cycle['income'] * 100) }}%;"></span>
                            </span>
                            <span class="num" title="Parte da receita do ciclo">{{ $groupShare }}</span>
                        @endif
                    </span>
                    <x-money class="num" :value="$group['total']" />
                </summary>
                <div class="d2-group-body">
                    @foreach($group['rows'] as $row)
                        <div class="d2-row">
                            @if($row['date'])
                                <span class="d2-row-date num">{{ $row['date']->format('d/m') }}</span>
                            @endif
                            <span class="d2-row-desc">
                                {{ $row['description'] }}
                                @if($row['tag'])
                                    <span @class(['d2-tag', 'is-recurring' => $row['recurring']])>{{ $row['tag'] }}</span>
                                @endif
                            </span>
                            <x-money class="num" :value="$row['amount']" />
                        </div>
                    @endforeach
                </div>
            </details>
        @empty
            <p class="muted small mb-0">Nenhuma saída neste ciclo.</p>
        @endforelse
        <div class="d2-row d2-row-total">
            <span class="d2-row-desc">Total de saídas</span>
            <x-money class="num" :value="$cycle['expenses']" />
        </div>
    </div>
</div>
