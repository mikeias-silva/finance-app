@extends('layouts.app')

@section('title', 'Dashboard')

@push('styles')
<style>
    .projection-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 16px;
    }
    .progress-thin {
        height: 6px;
        border-radius: 10px;
        background: #f1f5f9;
    }
    .legend-dot {
        width: 10px; height: 10px;
        border-radius: 50%;
        display: inline-block;
        flex-shrink: 0;
    }
    .purchase-row-sm {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 7px 0;
        border-bottom: 1px solid #f1f5f9;
        font-size: 13px;
    }
    .purchase-row-sm:last-child { border-bottom: none; }
    .card-badge-btn {
        border: none;
        cursor: pointer;
        transition: background-color .2s ease;
    }
    .card-badge-btn:hover {
        background: #e2e8f0;
    }
</style>
@endpush

@section('content')

{{-- Cabeçalho --}}
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h5  mb-0">Projeção financeira</h1>
        <p class="text-muted small mb-0 mt-1">
            Próximos {{ $months }} meses a partir de {{ now()->translatedFormat('F Y') }}
        </p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <label class="text-muted small fw-medium me-1 mb-0">Horizonte:</label>
        <div class="btn-group btn-group-sm" role="group">
            @foreach([3, 6, 12] as $m)
                <a href="{{ route('dashboard', ['months' => $m, 'installments_month' => $installmentsMonth]) }}"
                   class="btn {{ $months == $m ? 'btn-primary' : 'btn-outline-secondary' }}">
                    {{ $m }}m
                </a>
            @endforeach
        </div>
    </div>
</div>

{{-- Cards de resumo do mês atual --}}
@php $current = $projection->first(); @endphp
@if($current)
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="stat-card green">
            <div class="stat-icon"><i class="bi bi-cash-stack"></i></div>
            <div class="stat-label">Receita este mês</div>
            <div class="stat-value">
                R${{ number_format($current['salary'] + $current['variable_income']->sum('amount'), 0, ',', '.') }}
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card red">
            <div class="stat-icon"><i class="bi bi-arrow-down-circle"></i></div>
            <div class="stat-label">Gastos este mês</div>
            <div class="stat-value">
                R${{ number_format($current['card_expenses']->sum('amount') + $current['fixed_expenses']->sum('amount') + $current['variable_expenses']->sum('amount'), 0, ',', '.') }}
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card {{ $current['balance'] >= 0 ? 'blue' : 'yellow' }}">
            <div class="stat-icon">
                <i class="bi bi-{{ $current['balance'] >= 0 ? 'piggy-bank' : 'exclamation-triangle' }}"></i>
            </div>
            <div class="stat-label">Saldo previsto</div>
            <div class="stat-value">
                R${{ number_format(abs($current['balance']), 0, ',', '.') }}
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card blue">
            <div class="stat-icon"><i class="bi bi-credit-card"></i></div>
            <div class="stat-label">Total faturas</div>
            <div class="stat-value">
                R${{ number_format($current['card_expenses']->sum('amount'), 0, ',', '.') }}
            </div>
        </div>
    </div>
</div>
@endif

{{-- Projeção mês a mês --}}
<div class="row g-4">

    {{-- Coluna principal: cards de projeção --}}
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span><i class="bi bi-calendar3 me-2 text-muted"></i>Projeção mês a mês</span>
            </div>
            <div class="card-body p-0">
                @php
                    $maxExpense = $projection->max(fn($m) =>
                        $m['card_expenses']->sum('amount') + $m['fixed_expenses']->sum('amount') + $m['variable_expenses']->sum('amount')
                    );
                @endphp

                @foreach($projection as $yearMonth => $data)
                @php
                    $income  = $data['salary'] + $data['variable_income']->sum('amount');
                    $expense = $data['card_expenses']->sum('amount') + $data['fixed_expenses']->sum('amount') + $data['variable_expenses']->sum('amount');
                    $balance = $data['balance'];
                    $pct     = $maxExpense > 0 ? round(($expense / $maxExpense) * 100) : 0;
                    $isFirst = $loop->first;
                    $monthPayload = [
                        'year_month'        => $yearMonth,
                        'month_label'       => $data['month']->translatedFormat('F Y'),
                        'salary'            => $data['salary'],
                        'variable_income'   => $data['variable_income']->values(),
                        'card_expenses'     => $data['card_expenses']->values(),
                        'fixed_expenses'    => $data['fixed_expenses']->values(),
                        'variable_expenses' => $data['variable_expenses']->values(),
                        'balance'           => $balance,
                    ];
                @endphp
                <div class="px-4 py-3 {{ !$loop->last ? 'border-bottom' : '' }} {{ $isFirst ? 'bg-light bg-opacity-50' : '' }}">
                    <div class="d-flex align-items-start justify-content-between mb-2">
                        <div style="flex:1;min-width:0;">
                            <div class="d-flex align-items-center gap-2">
                                <span class="text-capitalize" style="font-size:14px;">
                                    {{ $data['month']->translatedFormat('F Y') }}
                                    @if($isFirst)
                                        <span class="badge bg-secondary ms-1" style="font-size:10px;vertical-align:middle;">atual</span>
                                    @endif
                                </span>
                                <button type="button"
                                        class="btn btn-sm btn-outline-secondary py-0 px-2"
                                        style="font-size:11px;border-radius:6px;"
                                        title="Ver detalhes de {{ $data['month']->translatedFormat('F Y') }}"
                                        data-bs-toggle="modal"
                                        data-bs-target="#monthDetailModal"
                                        data-month='@json($monthPayload)'>
                                    <i class="bi bi-list-ul me-1"></i>detalhes
                                </button>
                            </div>
                            <div class="text-muted mt-1" style="font-size:11px;">
                                <i class="bi bi-calendar2-range me-1"></i>
                                {{ $data['period_start']->translatedFormat('d M') }}
                                –
                                {{ $data['period_end']->translatedFormat('d M') }}
                            </div>
                            <div class="d-flex gap-3 mt-1">
                                <span class="small text-muted">
                                    <i class="bi bi-arrow-up text-success"></i>
                                    R${{ number_format($income, 2, ',', '.') }}
                                </span>
                                <span class="small text-muted">
                                    <i class="bi bi-arrow-down text-danger"></i>
                                    R${{ number_format($expense, 2, ',', '.') }}
                                </span>
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="" style="font-family:'DM Mono',monospace;font-size:15px;color:{{ $balance >= 0 ? 'var(--green)' : 'var(--red)' }}">
                                {{ $balance >= 0 ? '+' : '' }}R${{ number_format($balance, 2, ',', '.') }}
                            </div>
                            <div class="small text-muted">saldo</div>
                        </div>
                    </div>

                    {{-- Barra de gastos --}}
                    <div class="progress-thin">
                        <div class="bg-{{ $balance >= 0 ? 'success' : 'danger' }} rounded"
                             style="height:6px;width:{{ $pct }}%;transition:width .4s ease;"></div>
                    </div>

                    {{-- Faturas por cartão --}}
                    @if($data['card_expenses']->count() > 0)
                    <div class="d-flex flex-wrap gap-1 mt-2">
                        @foreach($data['card_expenses']->groupBy('card') as $cardName => $items)
                            <button type="button"
                                    class="card-badge card-badge-btn"
                                    title="Fatura {{ $cardName }}"
                                    data-bs-toggle="modal"
                                    data-bs-target="#cardExpensesModal"
                                    data-card-name="{{ $cardName }}"
                                    data-month-label="{{ $data['month']->translatedFormat('F Y') }}"
                                    data-items='@json($items->values())'>
                                <span class="dot" style="background:{{ $items->first()['color'] ?? '#94a3b8' }}"></span>
                                {{ $cardName }}
                                <strong>R${{ number_format($items->sum('amount'), 0, ',', '.') }}</strong>
                            </button>
                        @endforeach
                    </div>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Coluna lateral: detalhes do mês atual --}}
    <div class="col-lg-4">

        {{-- Gastos fixos --}}
        <div class="card mb-3">
            <div class="card-header">
                <i class="bi bi-receipt me-2 text-muted"></i>Gastos fixos
            </div>
            <div class="card-body py-2 px-3">
                @if($current && $current['fixed_expenses']->count() > 0)
                    @foreach($current['fixed_expenses'] as $expense)
                    <div class="purchase-row-sm">
                        <span class="text-truncate me-2" style="max-width:60%">{{ $expense['description'] }}</span>
                        <span class="fw-medium" style="font-family:'DM Mono',monospace;">
                            R${{ number_format($expense['amount'], 2, ',', '.') }}
                        </span>
                    </div>
                    @endforeach
                    <div class="d-flex justify-content-between pt-2 mt-1 border-top">
                        <span class="small  text-muted">Total</span>
                        <span class="" style="font-family:'DM Mono',monospace;">
                            R${{ number_format($current['fixed_expenses']->sum('amount'), 2, ',', '.') }}
                        </span>
                    </div>
                @else
                    <p class="text-muted small py-2 mb-0">Nenhum gasto fixo cadastrado.</p>
                @endif
            </div>
        </div>

        {{-- Compras parceladas este mês --}}
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span><i class="bi bi-credit-card me-2 text-muted"></i>Parcelas por mês</span>
                <div class="d-flex align-items-center gap-2">
                    <form method="GET" action="{{ route('dashboard') }}" class="d-flex align-items-center gap-2">
                        <input type="hidden" name="months" value="{{ $months }}">
                        <select name="installments_month" class="form-select form-select-sm" onchange="this.form.submit()">
                            @foreach($projection as $yearMonth => $data)
                                <option value="{{ $yearMonth }}" @selected($installmentsMonth === $yearMonth)>
                                    {{ $data['month']->translatedFormat('M/Y') }}
                                </option>
                            @endforeach
                        </select>
                    </form>
                    <a href="{{ route('purchases.create') }}" class="btn btn-sm btn-primary">
                        <i class="bi bi-plus"></i>
                    </a>
                </div>
            </div>
            <div class="card-body py-2 px-3">
                @if($selectedInstallmentsMonth && $selectedInstallmentsMonth['card_expenses']->count() > 0)
                    @foreach($selectedInstallmentsMonth['card_expenses'] as $item)
                        <div class="purchase-row-sm">
                            <div class="d-flex align-items-center gap-2" style="min-width:0;flex:1;">
                                <span class="legend-dot flex-shrink-0" style="background:{{ $item['color'] ?? '#94a3b8' }}"></span>
                                <span class="text-truncate small">{{ $item['description'] }}</span>
                                @if($item['is_recurring'])
                                    <span class="badge bg-warning text-dark flex-shrink-0" style="font-size:10px;">
                            <i class="bi bi-arrow-repeat"></i>
                        </span>
                                @else
                                    <span class="badge bg-light text-muted border flex-shrink-0" style="font-size:10px;">
                            {{ $item['current_installment'] }}/{{ $item['installments'] }}
                        </span>
                                @endif
                            </div>
                            <span class="fw-medium flex-shrink-0 ms-2" style="font-family:'DM Mono',monospace;font-size:12px;">
                    R${{ number_format($item['amount'], 2, ',', '.') }}
                </span>
                        </div>
                    @endforeach

                    <div class="d-flex justify-content-between pt-2 mt-1 border-top">
                        <span class="small  text-muted">Total faturas</span>
                        <span class="" style="font-family:'DM Mono',monospace;font-size:13px;">
                    R${{ number_format($selectedInstallmentsMonth['card_expenses']->sum('amount'), 2, ',', '.') }}
                </span>
                    </div>
                @else
                    <p class="text-muted small py-2 mb-0">Nenhuma parcela neste mês.</p>
                @endif
            </div>
        </div>

    </div>
</div>

{{-- Acúmulo de saldo mês a mês --}}
@php
    $accumulated = $totalWithYield ?? 0;
    $accumulatedData = [];
    foreach ($projection as $yearMonth => $data) {
        $accumulated += $data['saldo_cc'];
        $accumulatedData[$yearMonth] = [
            'month'       => $data['month'],
            'balance'     => $data['balance'],
            'saldo_cc'    => $data['saldo_cc'],
            'rdb_net'     => $data['rdb_net'],
            'accumulated' => $accumulated,
        ];
    }
    $maxAcc = collect($accumulatedData)->max(fn($d) => abs($d['accumulated']));
@endphp

<div class="card mt-4">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-bar-chart-line me-2 text-muted"></i>
        <span>Acúmulo de saldo mês a mês</span>
    </div>
    <div class="card-body py-3 px-4">
        <div class="d-flex align-items-end gap-3 overflow-auto pb-1" style="min-height:120px;">
            @foreach($accumulatedData as $yearMonth => $d)
            @php
                $pct   = $maxAcc > 0 ? min(100, round((abs($d['accumulated']) / $maxAcc) * 100)) : 0;
                $color = $d['accumulated'] >= 0 ? 'var(--green, #22c55e)' : 'var(--red, #ef4444)';
                $isFirst = $loop->first;
            @endphp
            <div class="d-flex flex-column align-items-center flex-shrink-0" style="min-width:72px;">
                <div class=" mb-1" style="font-family:'DM Mono',monospace;font-size:11px;color:{{ $color }};">
                    {{ $d['accumulated'] >= 0 ? '+' : '' }}R${{ number_format($d['accumulated'], 0, ',', '.') }}
                </div>
                <div class="rounded" style="width:40px;height:{{ max(6, $pct) }}px;background:{{ $color }};opacity:0.85;transition:height .4s ease;"></div>
                <div class="mt-2 text-muted text-capitalize text-center" style="font-size:11px;line-height:1.2;">
                    {{ $d['month']->translatedFormat('M') }}<br>
                    <span style="font-size:10px;">{{ $d['month']->format('Y') }}</span>
                </div>
                @if($isFirst)
                    <span class="badge bg-secondary mt-1" style="font-size:9px;">atual</span>
                @endif
            </div>
            @endforeach
        </div>

        {{-- Legenda de saldo mensal + acumulado --}}
        <div class="mt-3 border-top pt-3">
            <div class="row g-2">
                @foreach($accumulatedData as $yearMonth => $d)
                <div class="col-6 col-md-4 col-lg-2">
                    <div class="d-flex flex-column" style="font-size:12px;">
                        <span class="text-muted text-capitalize ">{{ $d['month']->translatedFormat('M/Y') }}</span>
                        <span style="color:{{ $d['saldo_cc'] >= 0 ? 'var(--green,#22c55e)' : 'var(--red,#ef4444)' }};">
                            C/C: {{ $d['saldo_cc'] >= 0 ? '+' : '' }}R${{ number_format($d['saldo_cc'], 0, ',', '.') }}
                        </span>
                        @if($d['rdb_net'] != 0)
                        <span class="text-muted">
                            RDB: {{ $d['rdb_net'] > 0 ? '-' : '+' }}R${{ number_format(abs($d['rdb_net']), 0, ',', '.') }}
                        </span>
                        @endif
                        <span class="" style="color:{{ $d['accumulated'] >= 0 ? 'var(--green,#22c55e)' : 'var(--red,#ef4444)' }};">
                            Acum.: {{ $d['accumulated'] >= 0 ? '+' : '' }}R${{ number_format($d['accumulated'], 0, ',', '.') }}
                        </span>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

{{-- Resumo de investimentos --}}
@if($investmentData->count() > 0)
<div class="card mt-4">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-piggy-bank me-2 text-muted"></i>Investimentos</span>
        <a href="{{ route('investments.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-right me-1"></i>Ver detalhes
        </a>
    </div>
    <div class="card-body px-4 py-3">

        {{-- Totalizadores --}}
        <div class="row g-3 mb-4">
            <div class="col-4">
                <div class="d-flex flex-column">
                    <span class="text-muted" style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.6px;">Total investido</span>
                    <span style="font-family:'DM Mono',monospace;font-size:18px;color:var(--blue);">
                        R${{ number_format($totalInvested, 2, ',', '.') }}
                    </span>
                </div>
            </div>
            <div class="col-4">
                <div class="d-flex flex-column">
                    <span class="text-muted" style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.6px;">Rendimento acumulado</span>
                    <span style="font-family:'DM Mono',monospace;font-size:18px;color:var(--green);">
                        +R${{ number_format($totalYield, 2, ',', '.') }}
                    </span>
                </div>
            </div>
            <div class="col-4">
                <div class="d-flex flex-column">
                    <span class="text-muted" style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.6px;">Total c/ rendimento</span>
                    <span style="font-family:'DM Mono',monospace;font-size:18px;color:var(--text-primary);">
                        R${{ number_format($totalWithYield, 2, ',', '.') }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Card por caixinha --}}
        <div class="row g-3">
            @foreach($investmentData as $item)
            @php $box = $item['box']; @endphp
            <div class="col-md-6 col-lg-4">
                <div class="p-3 rounded-3 border" style="border-left: 4px solid {{ $box->color }} !important;">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="rounded-circle" style="width:10px;height:10px;background:{{ $box->color }};display:inline-block;flex-shrink:0;"></span>
                            <span class="" style="font-size:13px;">{{ $box->name }}</span>
                        </div>
                        <span class="badge" style="background:{{ $box->color }}20;color:{{ $box->color }};font-size:10px;">
                            {{ number_format($box->rate_percentage, 0) }}% CDI
                        </span>
                    </div>

                    <div class="d-flex justify-content-between align-items-end mb-2">
                        <div>
                            <div class="text-muted" style="font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;">Saldo</div>
                            <div style="font-family:'DM Mono',monospace;font-size:15px;">
                                R${{ number_format($item['balance'], 2, ',', '.') }}
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="text-muted" style="font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;">Rendimento</div>
                            <div style="font-family:'DM Mono',monospace;font-size:13px;color:var(--green);">
                                +R${{ number_format($item['yield'], 2, ',', '.') }}
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="text-muted" style="font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;">Total</div>
                            <div style="font-family:'DM Mono',monospace;font-size:13px;">
                                R${{ number_format($item['total'], 2, ',', '.') }}
                            </div>
                        </div>
                    </div>

                    @if($box->max_amount)
                    @php $pct = min(100, round(($item['balance'] / $box->max_amount) * 100)); @endphp
                    <div>
                        <div class="d-flex justify-content-between mb-1">
                            <span style="font-size:10px;color:var(--text-muted);">Capacidade usada</span>
                            <span style="font-size:10px;color:var(--text-muted);font-family:'DM Mono',monospace;">{{ $pct }}% · R${{ number_format($box->max_amount - $item['balance'], 0, ',', '.') }} livre</span>
                        </div>
                        <div style="height:5px;background:#e2e8f0;border-radius:999px;overflow:hidden;">
                            <div style="height:100%;width:{{ $pct }}%;background:{{ $box->color }};border-radius:999px;transition:width .4s ease;"></div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
            @endforeach
        </div>

    </div>
</div>
@endif

{{-- Modal: detalhes completos do mês --}}
<div class="modal fade" id="monthDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title mb-0" id="monthDetailModalTitle">Detalhes do mês</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body px-4 py-3" id="monthDetailModalBody">
                {{-- preenchido via JS --}}
            </div>
            <div class="modal-footer d-flex justify-content-between align-items-center">
                <div class="d-flex flex-column" style="font-size:13px;">
                    <span class="text-muted small">Saldo previsto</span>
                    <span id="monthDetailBalance" style="font-family:'DM Mono',monospace;font-size:16px;"></span>
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="cardExpensesModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title mb-0" data-role="title">Fatura</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3" data-role="month"></p>
                <div data-role="list" class="d-flex flex-column gap-2"></div>
            </div>
            <div class="modal-footer d-flex justify-content-between">
                <span class="small  text-muted">Total</span>
                <span class="" style="font-family:'DM Mono',monospace;" data-role="total">R$0,00</span>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    (() => {
        const money = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
        const dateF = (d) => d ? new Date(d).toLocaleDateString('pt-BR', { day:'2-digit', month:'2-digit', year:'numeric' }) : '';

        function makeRow(description, amount, extra = '') {
            return `<div class="purchase-row-sm">
                <span class="text-truncate me-2" style="max-width:60%;">${description}${extra ? ' <span class="text-muted" style="font-size:11px;">' + extra + '</span>' : ''}</span>
                <span style="font-family:'DM Mono',monospace;font-size:13px;">${money.format(amount)}</span>
            </div>`;
        }

        function makeSection(title, iconClass, colorClass, rows, total) {
            if (!rows.length) return '';
            return `<div class="mb-4">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="bi ${iconClass} ${colorClass}"></i>
                    <span class="fw-semibold" style="font-size:13px;">${title}</span>
                </div>
                <div class="border rounded-3 px-3 py-1">
                    ${rows.join('')}
                    <div class="d-flex justify-content-between pt-2 mt-1 border-top">
                        <span class="small text-muted">Total</span>
                        <span class="fw-medium" style="font-family:'DM Mono',monospace;font-size:13px;">${money.format(total)}</span>
                    </div>
                </div>
            </div>`;
        }

        const modal = document.getElementById('monthDetailModal');
        if (!modal) return;

        modal.addEventListener('show.bs.modal', (event) => {
            const trigger = event.relatedTarget;
            if (!trigger) return;

            const d = JSON.parse(trigger.getAttribute('data-month') || '{}');
            const body = document.getElementById('monthDetailModalBody');
            const balanceEl = document.getElementById('monthDetailBalance');
            document.getElementById('monthDetailModalTitle').textContent = d.month_label || 'Detalhes';

            let html = '';

            // ── Receitas ──────────────────────────────────────────────────────
            const incomeRows = [];
            let totalIncome = 0;

            if (d.salary > 0) {
                incomeRows.push(makeRow('Salário', d.salary));
                totalIncome += Number(d.salary);
            }
            (d.variable_income || []).forEach(i => {
                incomeRows.push(makeRow(i.description, i.amount, dateF(i.received_at)));
                totalIncome += Number(i.amount);
            });
            html += makeSection('Receitas', 'bi-arrow-up-circle-fill', 'text-success', incomeRows, totalIncome);

            // ── Despesas por cartão ───────────────────────────────────────────
            const cardGroups = {};
            (d.card_expenses || []).forEach(e => {
                if (!cardGroups[e.card]) cardGroups[e.card] = { items: [], color: e.color, total: 0 };
                cardGroups[e.card].items.push(e);
                cardGroups[e.card].total += Number(e.amount);
            });

            let totalCards = 0;
            Object.entries(cardGroups).forEach(([cardName, group]) => {
                const rows = group.items.map(e => {
                    const badge = e.is_recurring
                        ? '<span class="badge bg-warning text-dark ms-1" style="font-size:10px;"><i class="bi bi-arrow-repeat"></i> recorrente</span>'
                        : `<span class="badge bg-light text-muted border ms-1" style="font-size:10px;">${e.current_installment}/${e.installments}</span>`;
                    return `<div class="purchase-row-sm">
                        <span class="d-flex align-items-center gap-1 text-truncate me-2" style="max-width:65%;">
                            <span style="width:8px;height:8px;border-radius:50%;background:${group.color || '#94a3b8'};flex-shrink:0;display:inline-block;"></span>
                            ${e.description}${badge}
                        </span>
                        <span style="font-family:'DM Mono',monospace;font-size:13px;">${money.format(e.amount)}</span>
                    </div>`;
                });
                if (rows.length) {
                    html += `<div class="mb-4">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span style="width:10px;height:10px;border-radius:50%;background:${group.color || '#94a3b8'};display:inline-block;flex-shrink:0;"></span>
                            <i class="bi bi-credit-card text-danger"></i>
                            <span class="fw-semibold" style="font-size:13px;">Cartão: ${cardName}</span>
                        </div>
                        <div class="border rounded-3 px-3 py-1">
                            ${rows.join('')}
                            <div class="d-flex justify-content-between pt-2 mt-1 border-top">
                                <span class="small text-muted">Total ${cardName}</span>
                                <span class="fw-medium" style="font-family:'DM Mono',monospace;font-size:13px;">${money.format(group.total)}</span>
                            </div>
                        </div>
                    </div>`;
                    totalCards += group.total;
                }
            });

            // ── Gastos fixos ──────────────────────────────────────────────────
            const fixedRows = (d.fixed_expenses || []).map(e => makeRow(e.description, e.amount));
            const totalFixed = (d.fixed_expenses || []).reduce((s, e) => s + Number(e.amount), 0);
            html += makeSection('Gastos fixos', 'bi-receipt', 'text-warning', fixedRows, totalFixed);

            // ── Movimentações da conta ────────────────────────────────────────
            const varRows = (d.variable_expenses || []).map(e => makeRow(e.description, e.amount, dateF(e.paid_at)));
            const totalVar = (d.variable_expenses || []).reduce((s, e) => s + Number(e.amount), 0);
            html += makeSection('Movimentações da conta', 'bi-bank', 'text-danger', varRows, totalVar);

            // ── Totalizador ───────────────────────────────────────────────────
            const totalExpense = totalCards + totalFixed + totalVar;
            html += `<div class="border-top pt-3 mt-2">
                <div class="d-flex justify-content-between mb-1">
                    <span class="small text-muted fw-semibold">Total receitas</span>
                    <span style="font-family:'DM Mono',monospace;font-size:13px;color:var(--bs-success);">${money.format(totalIncome)}</span>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="small text-muted fw-semibold">Total despesas</span>
                    <span style="font-family:'DM Mono',monospace;font-size:13px;color:var(--bs-danger);">${money.format(totalExpense)}</span>
                </div>
            </div>`;

            body.innerHTML = html || '<p class="text-muted small">Sem dados para este mês.</p>';

            const balance = Number(d.balance);
            balanceEl.textContent = (balance >= 0 ? '+' : '') + money.format(balance);
            balanceEl.style.color = balance >= 0 ? 'var(--bs-success)' : 'var(--bs-danger)';
        });
    })();

    (() => {
        const modal = document.getElementById('cardExpensesModal');
        if (!modal) return;

        const money = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
        const titleEl = modal.querySelector('[data-role="title"]');
        const monthEl = modal.querySelector('[data-role="month"]');
        const listEl = modal.querySelector('[data-role="list"]');
        const totalEl = modal.querySelector('[data-role="total"]');

        modal.addEventListener('show.bs.modal', (event) => {
            const trigger = event.relatedTarget;
            if (!trigger) return;

            const cardName = trigger.getAttribute('data-card-name') || 'Cartão';
            const monthLabel = trigger.getAttribute('data-month-label') || '';
            const items = JSON.parse(trigger.getAttribute('data-items') || '[]');

            titleEl.textContent = `Gastos no ${cardName}`;
            monthEl.textContent = monthLabel;
            listEl.innerHTML = '';

            if (!items.length) {
                const empty = document.createElement('p');
                empty.className = 'text-muted small mb-0';
                empty.textContent = 'Sem gastos para este cartão no mês selecionado.';
                listEl.appendChild(empty);
                totalEl.textContent = money.format(0);
                return;
            }

            let total = 0;
            items.forEach((item) => {
                total += Number(item.amount || 0);

                const row = document.createElement('div');
                row.className = 'purchase-row-sm';

                const left = document.createElement('div');
                left.className = 'd-flex align-items-center gap-2';

                const description = document.createElement('span');
                description.className = 'small';
                description.textContent = item.description || '-';

                const badge = document.createElement('span');
                badge.className = item.is_recurring
                    ? 'badge bg-warning text-dark'
                    : 'badge bg-light text-muted border';
                badge.style.fontSize = '10px';
                badge.textContent = item.is_recurring
                    ? 'recorrente'
                    : `${item.current_installment}/${item.installments}`;

                left.appendChild(description);
                left.appendChild(badge);

                const amount = document.createElement('span');
                amount.className = 'fw-medium';
                amount.style.fontFamily = "'DM Mono',monospace";
                amount.textContent = money.format(Number(item.amount || 0));

                row.appendChild(left);
                row.appendChild(amount);
                listEl.appendChild(row);
            });

            totalEl.textContent = money.format(total);
        });
    })();
</script>
@endpush
