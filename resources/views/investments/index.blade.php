@extends('layouts.app')

@section('title', 'Investimentos')

@push('styles')
<style>
    .investment-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 16px;
    }
    .investment-card {
        border: 1px solid var(--card-border);
        border-radius: 14px;
        background: #fff;
        box-shadow: 0 1px 3px rgba(15, 23, 42, .04);
        overflow: hidden;
    }
    .investment-card .color-bar {
        height: 5px;
    }
    .investment-card .card-body {
        padding: 18px;
    }
    .investment-amount {
        font-family: 'DM Mono', monospace;
        font-size: 1.05rem;
        font-weight: 500;
    }
    .investment-total {
        font-family: 'DM Mono', monospace;
        font-size: 1.3rem;
        font-weight: 600;
    }
    .progress-thin {
        height: 8px;
        background: #e2e8f0;
        border-radius: 999px;
        overflow: hidden;
    }
    .progress-thin .progress-bar {
        border-radius: 999px;
    }
    .section-note {
        font-size: 12px;
        color: var(--text-muted);
    }
    .box-meta {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 12px;
        color: var(--text-muted);
    }
    .box-dot {
        width: 11px;
        height: 11px;
        border-radius: 50%;
        flex-shrink: 0;
    }
    .mono {
        font-family: 'DM Mono', monospace;
    }
</style>
@endpush

@section('content')
@if($errors->any())
    <div class="alert alert-danger mb-4">
        <div class="d-flex align-items-start gap-2">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div>
                <div class="fw-semibold mb-1">Corrija os campos abaixo:</div>
                <ul class="mb-0 ps-3">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
@endif

<div class="d-flex align-items-center justify-content-between mb-4 gap-3 flex-wrap">
    <div>
        <h1 class="h5 fw-semibold mb-0">Investimentos</h1>
        <p class="text-muted small mb-0 mt-1">Gerencie caixinhas, rendimentos e alocação do saldo livre projetado.</p>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <div class="stat-card blue h-100">
            <div class="stat-icon"><i class="bi bi-wallet2"></i></div>
            <div class="stat-label">Total investido</div>
            <div class="stat-value">R$ {{ number_format($totalInvested, 2, ',', '.') }}</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card green h-100">
            <div class="stat-icon"><i class="bi bi-graph-up-arrow"></i></div>
            <div class="stat-label">Rendimento total</div>
            <div class="stat-value">R$ {{ number_format($totalYield, 2, ',', '.') }}</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card green h-100">
            <div class="stat-icon"><i class="bi bi-piggy-bank"></i></div>
            <div class="stat-label">Total c/ rendimento</div>
            <div class="stat-value">R$ {{ number_format($totalWithYield, 2, ',', '.') }}</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card yellow h-100">
            <div class="stat-icon"><i class="bi bi-percent"></i></div>
            <div class="stat-label">CDI atual</div>
            <div class="stat-value">{{ number_format($cdiRate?->rate ?? 13.25, 2, ',', '.') }}%</div>
            <div class="section-note">{{ optional($cdiRate?->effective_from)->format('d/m/Y') ?? 'Taxa padrão aplicada' }}</div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span><i class="bi bi-piggy-bank me-2 text-muted"></i>Caixinhas ativas</span>
                <span class="small text-muted">{{ $boxes->count() }} {{ \Illuminate\Support\Str::plural('caixinha', $boxes->count()) }}</span>
            </div>
            <div class="card-body">
                @if($boxData->isEmpty())
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-safe2 fs-2 d-block mb-2 opacity-25"></i>
                        Nenhuma caixinha ativa cadastrada.
                    </div>
                @else
                    <div class="investment-grid">
                        @foreach($boxData as $item)
                            @php
                                $box = $item['box'];
                                $progress = (!is_null($box->max_amount) && $box->max_amount > 0)
                                    ? min(100, ($item['balance'] / $box->max_amount) * 100)
                                    : null;
                            @endphp
                            <div class="investment-card">
                                <div class="color-bar" style="background: {{ $box->color }};"></div>
                                <div class="card-body">
                                    <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
                                        <div>
                                            <div class="d-flex align-items-center gap-2 mb-1">
                                                <span class="box-dot" style="background: {{ $box->color }};"></span>
                                                <h2 class="h6 fw-semibold mb-0">{{ $box->name }}</h2>
                                            </div>
                                            <div class="box-meta">
                                                <span>{{ number_format($box->rate_percentage, 0, ',', '.') }}% CDI</span>
                                                <span>•</span>
                                                <span>{{ $box->max_amount ? 'Com teto' : 'Sem limite' }}</span>
                                            </div>
                                        </div>
                                        <span class="card-badge">
                                            <span class="dot" style="background: {{ $box->color }};"></span>
                                            Ativa
                                        </span>
                                    </div>

                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between small text-muted mb-1">
                                            <span>Saldo atual</span>
                                            <span class="mono">R$ {{ number_format($item['balance'], 2, ',', '.') }}</span>
                                        </div>
                                        <div class="d-flex justify-content-between small mb-1">
                                            <span class="text-muted">Rendimento acumulado</span>
                                            <span class="mono" style="color: var(--green);">+R$ {{ number_format($item['yield'], 2, ',', '.') }}</span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-end">
                                            <span class="text-muted small">Total</span>
                                            <span class="investment-total">R$ {{ number_format($item['total'], 2, ',', '.') }}</span>
                                        </div>
                                    </div>

                                    @if(!is_null($box->max_amount))
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between section-note mb-2">
                                                <span>Uso do teto</span>
                                                <span class="mono">R$ {{ number_format($item['balance'], 2, ',', '.') }} / R$ {{ number_format($box->max_amount, 2, ',', '.') }}</span>
                                            </div>
                                            <div class="progress-thin mb-2">
                                                <div class="progress-bar bg-success" role="progressbar" style="width: {{ round($progress ?? 0, 2) }}%"></div>
                                            </div>
                                            <div class="section-note">
                                                Disponível: <span class="mono">R$ {{ number_format($item['available'] ?? 0, 2, ',', '.') }}</span>
                                            </div>
                                        </div>
                                    @else
                                        <div class="mb-3 section-note">Caixinha sem teto máximo para novos aportes.</div>
                                    @endif

                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-primary btn-sm flex-fill" data-bs-toggle="modal" data-bs-target="#depositModal{{ $box->id }}">
                                            <i class="bi bi-plus-lg"></i> Depositar
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm flex-fill" data-bs-toggle="modal" data-bs-target="#withdrawalModal{{ $box->id }}">
                                            <i class="bi bi-dash-lg"></i> Sacar
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <i class="bi bi-arrow-left-right me-2 text-muted"></i>Alocar saldo livre
            </div>
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Saldo projetado do mês</label>
                        <div class="form-control bg-light mono">R$ {{ number_format($projectedBalance, 2, ',', '.') }}</div>
                    </div>
                    <div class="col-md-8">
                        <p class="section-note mb-0">Use a projeção do mês atual para registrar um novo aporte diretamente em uma das caixinhas.</p>
                    </div>
                </div>

                <form method="POST" id="allocation-form" class="row g-3 mt-1" action="{{ $boxes->first() ? route('investments.deposit', $boxes->first()) : '#' }}">
                    @csrf
                    <div class="col-md-4">
                        <label for="allocation-box" class="form-label">Caixinha</label>
                        <select id="allocation-box" class="form-select" {{ $boxes->isEmpty() ? 'disabled' : '' }}>
                            @foreach($boxes as $box)
                                <option value="{{ $box->id }}" data-name="{{ $box->name }}" data-available="{{ $boxData->firstWhere('box.id', $box->id)['available'] ?? '' }}">
                                    {{ $box->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="allocation-amount" class="form-label">Valor</label>
                        <input id="allocation-amount" type="number" name="amount" min="0.01" step="0.01" class="form-control" value="{{ $projectedBalance > 0 ? number_format($projectedBalance, 2, '.', '') : '' }}" required>
                    </div>
                    <div class="col-md-3">
                        <label for="allocation-date" class="form-label">Data</label>
                        <input id="allocation-date" type="date" name="transacted_at" class="form-control" value="{{ now()->toDateString() }}" required>
                    </div>
                    <div class="col-md-2 d-grid">
                        <button type="submit" id="allocation-submit" class="btn btn-primary" {{ $boxes->isEmpty() || $projectedBalance <= 0 ? 'disabled' : '' }}>
                            <i class="bi bi-check2-circle"></i> Alocar
                        </button>
                    </div>
                    <div class="col-12">
                        <label for="allocation-notes" class="form-label">Observações</label>
                        <input id="allocation-notes" type="text" name="notes" class="form-control" maxlength="255" value="Alocação de saldo livre">
                        <div class="section-note mt-2" id="allocation-help">Selecione uma caixinha para concluir o aporte.</div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-sliders me-2 text-muted"></i>Configurações
            </div>
            <div class="card-body">
                <div class="accordion" id="investmentSettingsAccordion">
                    <div class="accordion-item border rounded-3 mb-3 overflow-hidden">
                        <h2 class="accordion-header">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCdi" aria-expanded="true">
                                Atualizar taxa CDI
                            </button>
                        </h2>
                        <div id="collapseCdi" class="accordion-collapse collapse show" data-bs-parent="#investmentSettingsAccordion">
                            <div class="accordion-body">
                                <form method="POST" action="{{ route('investments.cdi.store') }}" class="row g-3">
                                    @csrf
                                    <div class="col-12">
                                        <label class="form-label">Taxa anual (%)</label>
                                        <input type="number" name="rate" min="0" max="100" step="0.0001" class="form-control" value="{{ old('rate', $cdiRate?->rate ?? 13.25) }}" required>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Vigência</label>
                                        <input type="date" name="effective_from" class="form-control" value="{{ old('effective_from', now()->toDateString()) }}" required>
                                    </div>
                                    <div class="col-12 d-grid">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-save"></i> Salvar CDI
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item border rounded-3 overflow-hidden">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseBox" aria-expanded="false">
                                Adicionar nova caixinha
                            </button>
                        </h2>
                        <div id="collapseBox" class="accordion-collapse collapse" data-bs-parent="#investmentSettingsAccordion">
                            <div class="accordion-body">
                                <form method="POST" action="{{ route('investments.boxes.store') }}" class="row g-3">
                                    @csrf
                                    <div class="col-12">
                                        <label class="form-label">Nome</label>
                                        <input type="text" name="name" class="form-control" maxlength="255" value="{{ old('name') }}" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Cor</label>
                                        <input type="color" name="color" class="form-control form-control-color w-100" style="height:44px;" value="{{ old('color', '#10b981') }}" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">% do CDI</label>
                                        <input type="number" name="rate_percentage" min="1" max="500" step="0.01" class="form-control" value="{{ old('rate_percentage', 100) }}" required>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Teto da caixinha</label>
                                        <input type="number" name="max_amount" min="0" step="0.01" class="form-control" value="{{ old('max_amount') }}" placeholder="Deixe em branco para sem limite">
                                    </div>
                                    <div class="col-12 d-grid">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-plus-lg"></i> Criar caixinha
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@foreach($boxData as $item)
    @php($box = $item['box'])
    <div class="modal fade" id="depositModal{{ $box->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header">
                    <h2 class="modal-title fs-6 fw-semibold">Depositar em {{ $box->name }}</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <form method="POST" action="{{ route('investments.deposit', $box) }}">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Valor</label>
                            <input type="number" name="amount" min="0.01" step="0.01" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Data</label>
                            <input type="date" name="transacted_at" class="form-control" value="{{ now()->toDateString() }}" required>
                        </div>
                        <div>
                            <label class="form-label">Observações</label>
                            <input type="text" name="notes" class="form-control" maxlength="255" placeholder="Opcional">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Confirmar depósito</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="withdrawalModal{{ $box->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header">
                    <h2 class="modal-title fs-6 fw-semibold">Sacar de {{ $box->name }}</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <form method="POST" action="{{ route('investments.withdrawal', $box) }}">
                    @csrf
                    <div class="modal-body">
                        <div class="section-note mb-3">Saldo atual: <span class="mono">R$ {{ number_format($item['balance'], 2, ',', '.') }}</span></div>
                        <div class="mb-3">
                            <label class="form-label">Valor</label>
                            <input type="number" name="amount" min="0.01" step="0.01" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Data</label>
                            <input type="date" name="transacted_at" class="form-control" value="{{ now()->toDateString() }}" required>
                        </div>
                        <div>
                            <label class="form-label">Observações</label>
                            <input type="text" name="notes" class="form-control" maxlength="255" placeholder="Opcional">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Confirmar saque</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endforeach
@endsection

@push('scripts')
<script>
    (function () {
        const form = document.getElementById('allocation-form');
        const select = document.getElementById('allocation-box');
        const help = document.getElementById('allocation-help');
        const submit = document.getElementById('allocation-submit');

        if (!form || !select) {
            return;
        }

        const updateAllocationTarget = () => {
            const option = select.options[select.selectedIndex];

            if (!option) {
                form.action = '#';
                if (submit) submit.disabled = true;
                if (help) help.textContent = 'Selecione uma caixinha para concluir o aporte.';
                return;
            }

            form.action = `{{ url('/investments') }}/${option.value}/deposit`;

            const available = option.dataset.available;
            if (available !== '' && available !== undefined) {
                const formatted = Number(available).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                if (help) help.textContent = `Capacidade disponível em ${option.dataset.name}: R$ ${formatted}.`;
            } else if (help) {
                help.textContent = `${option.dataset.name} não possui teto máximo para novos aportes.`;
            }
        };

        updateAllocationTarget();
        select.addEventListener('change', updateAllocationTarget);
    })();
</script>
@endpush
