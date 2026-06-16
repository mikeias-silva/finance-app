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
        <h1 class="h5 fw-semibold mb-0">Projeção financeira</h1>
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
                R${{ number_format($current['card_expenses']->sum('amount') + $current['fixed_expenses']->sum('amount'), 0, ',', '.') }}
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
                        $m['card_expenses']->sum('amount') + $m['fixed_expenses']->sum('amount')
                    );
                @endphp

                @foreach($projection as $yearMonth => $data)
                @php
                    $income  = $data['salary'] + $data['variable_income']->sum('amount');
                    $expense = $data['card_expenses']->sum('amount') + $data['fixed_expenses']->sum('amount');
                    $balance = $data['balance'];
                    $pct     = $maxExpense > 0 ? round(($expense / $maxExpense) * 100) : 0;
                    $isFirst = $loop->first;
                @endphp
                <div class="px-4 py-3 {{ !$loop->last ? 'border-bottom' : '' }} {{ $isFirst ? 'bg-light bg-opacity-50' : '' }}">
                    <div class="d-flex align-items-start justify-content-between mb-2">
                        <div>
                            <span class="fw-semibold text-capitalize" style="font-size:14px;">
                                {{ $data['month']->translatedFormat('F Y') }}
                                @if($isFirst)
                                    <span class="badge bg-secondary ms-1" style="font-size:10px;vertical-align:middle;">atual</span>
                                @endif
                            </span>
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
                            <div class="fw-semibold" style="font-family:'DM Mono',monospace;font-size:15px;color:{{ $balance >= 0 ? 'var(--green)' : 'var(--red)' }}">
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
                        <span class="small fw-semibold text-muted">Total</span>
                        <span class="fw-semibold" style="font-family:'DM Mono',monospace;">
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
                        <span class="small fw-semibold text-muted">Total faturas</span>
                        <span class="fw-semibold" style="font-family:'DM Mono',monospace;font-size:13px;">
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
                <span class="small fw-semibold text-muted">Total</span>
                <span class="fw-semibold" style="font-family:'DM Mono',monospace;" data-role="total">R$0,00</span>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
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
