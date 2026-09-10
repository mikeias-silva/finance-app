{{-- Resultado da última importação do Nubank, no mesmo vocabulário visual do resto do app. --}}
@if(session('import_result'))
@php($import = session('import_result'))

@push('styles')
<style>
    .import-result .metric-label {
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .6px;
        color: var(--text-muted);
    }
    .import-result .metric-value {
        font-family: 'DM Mono', monospace;
        font-size: 22px;
        line-height: 1.2;
    }
    .import-result .section-title {
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .6px;
        color: var(--text-muted);
        padding: 14px 20px 6px;
    }
    .import-result .money { font-family: 'DM Mono', monospace; white-space: nowrap; }
    .import-result .scroll-area { max-height: 340px; overflow-y: auto; }
</style>
@endpush

<div class="card mb-4 import-result">

    <div class="card-header d-flex align-items-center gap-2 flex-wrap">
        <i class="bi bi-arrow-repeat"></i>
        <span>Importação do Nubank</span>

        @if($import['dry_run'])
            <span class="card-badge" style="background: var(--yellow-soft); color: var(--yellow-text);">
                <i class="bi bi-eye"></i> Simulação
            </span>
        @endif
        @if($import['replaced_investments'])
            <span class="card-badge" style="background: var(--blue-soft); color: var(--blue-text);">
                <i class="bi bi-piggy-bank"></i> Caixinhas regravadas
            </span>
        @endif

        <span class="text-muted fw-normal small ms-auto money">
            {{ \Carbon\Carbon::parse($import['from'])->format('d/m/Y') }}
            →
            {{ \Carbon\Carbon::parse($import['to'])->format('d/m/Y') }}
        </span>
        <button type="button" class="btn-close btn-sm" aria-label="Fechar"
                onclick="this.closest('.import-result').remove()"></button>
    </div>

    {{-- Números do ciclo --}}
    <div class="card-body d-flex flex-wrap gap-4 pb-3">
        <div>
            <div class="metric-label">{{ $import['dry_run'] ? 'Seriam gravados' : 'Gravados' }}</div>
            <div class="metric-value" style="color: var(--green-text);">{{ $import['created'] }}</div>
        </div>
        <div>
            <div class="metric-label">Já existiam</div>
            <div class="metric-value text-muted">{{ $import['skipped'] }}</div>
        </div>
        <div>
            <div class="metric-label">Confirmados</div>
            <div class="metric-value" style="color: var(--blue-text);">{{ $import['confirmed'] }}</div>
        </div>
        <div>
            <div class="metric-label">Lidos na API</div>
            <div class="metric-value text-muted">{{ $import['api_transaction_count'] }}</div>
        </div>
        @if($import['investment_movements'])
        <div>
            <div class="metric-label" title="Aplicações e resgates de caixinha">Movimentações RDB</div>
            <div class="metric-value text-muted">{{ $import['investment_movements'] }}</div>
        </div>
        @endif
    </div>

    @if($import['dry_run'])
        <div class="px-3 pb-2">
            <div class="alert alert-warning d-flex align-items-center gap-2 mb-0"
                 style="background: var(--yellow-soft); color: var(--yellow-text);">
                <i class="bi bi-info-circle-fill"></i>
                Nada foi gravado — esta é a prévia do que a importação faria.
            </div>
        </div>
    @endif

    @foreach($import['unknown_rates'] as $unknown)
        <div class="px-3 pb-2">
            <div class="alert alert-warning d-flex align-items-center gap-2 mb-0"
                 style="background: var(--yellow-soft); color: var(--yellow-text);">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <span>
                    <span class="money">R$ {{ number_format($unknown['balance'], 2, ',', '.') }}</span>
                    em posições de {{ $unknown['rate'] }}% do CDI sem caixinha cadastrada — ignorado.
                </span>
            </div>
        </div>
    @endforeach

    {{-- Salário do ciclo --}}
    @if($import['salary'])
        @php($diff = $import['salary']['difference'])
        <div class="section-title">Salário</div>
        <div class="px-3 pb-3">
            <div class="d-flex align-items-center gap-3 flex-wrap p-3 rounded"
                 style="background: var(--surface-alt);">
                <i class="bi bi-cash-stack" style="color: var(--green-text);"></i>
                <span>Recebido em {{ \Carbon\Carbon::parse($import['salary']['date'])->format('d/m') }}</span>
                <span class="money ms-auto">R$ {{ number_format($import['salary']['received'], 2, ',', '.') }}</span>
                @if($diff > 0)
                    <span class="card-badge" style="background: var(--green-soft); color: var(--green-text);">
                        <i class="bi bi-arrow-up"></i> R$ {{ number_format($diff, 2, ',', '.') }} acima do previsto
                    </span>
                @elseif($diff < 0)
                    <span class="card-badge" style="background: var(--yellow-soft); color: var(--yellow-text);">
                        <i class="bi bi-arrow-down"></i> R$ {{ number_format(abs($diff), 2, ',', '.') }} abaixo do previsto
                    </span>
                @else
                    <span class="card-badge" style="background: var(--green-soft); color: var(--green-text);">
                        <i class="bi bi-check-lg"></i> Igual ao previsto
                    </span>
                @endif
            </div>
        </div>
    @endif

    {{-- Lançamentos gravados --}}
    @if($import['entries'])
        <div class="section-title">
            {{ $import['dry_run'] ? 'Lançamentos que entrariam' : 'Lançamentos gravados' }}
            ({{ count($import['entries']) }})
        </div>
        <div class="table-responsive scroll-area">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th style="width:90px;">Data</th>
                        <th>Descrição</th>
                        <th class="text-end" style="width:140px;">Valor</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($import['entries'] as $entry)
                        @php($isExpense = $entry['type'] === 'expense')
                        <tr>
                            <td class="money text-muted">{{ \Carbon\Carbon::parse($entry['date'])->format('d/m') }}</td>
                            <td>
                                <i class="bi bi-{{ $isExpense ? 'arrow-down-circle' : 'arrow-up-circle' }} me-1"
                                   style="color: var(--{{ $isExpense ? 'red' : 'green' }}-text);"
                                   title="{{ $isExpense ? 'Despesa' : 'Receita' }}"></i>
                                {{ $entry['description'] }}
                            </td>
                            <td class="text-end money" style="color: var(--{{ $isExpense ? 'red' : 'green' }}-text);">
                                {{ $isExpense ? '−' : '+' }} R$ {{ number_format($entry['amount'], 2, ',', '.') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Gastos fixos e faturas encontrados no extrato --}}
    @if($import['fixed_expenses'])
        <div class="section-title">Gastos fixos e faturas ({{ count($import['fixed_expenses']) }})</div>
        <div class="table-responsive scroll-area">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th style="width:90px;">Data</th>
                        <th>Conta</th>
                        <th class="text-end" style="width:130px;">Pago</th>
                        <th class="text-end" style="width:130px;">Previsto</th>
                        <th class="text-end" style="width:200px;">Diferença</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($import['fixed_expenses'] as $fixed)
                        <tr>
                            <td class="money text-muted">{{ \Carbon\Carbon::parse($fixed['date'])->format('d/m') }}</td>
                            <td>{{ $fixed['label'] }}</td>
                            <td class="text-end money">R$ {{ number_format($fixed['paid'], 2, ',', '.') }}</td>
                            <td class="text-end money text-muted">
                                {{ $fixed['expected'] === null ? '—' : 'R$ '.number_format($fixed['expected'], 2, ',', '.') }}
                            </td>
                            <td class="text-end">
                                @if($fixed['difference'] === null)
                                    <span class="card-badge" style="background: var(--blue-soft); color: var(--blue-text);">
                                        <i class="bi bi-check-lg"></i> Pagamento confirmado
                                    </span>
                                @elseif($fixed['difference'] > 0)
                                    <span class="card-badge" style="background: var(--red-soft); color: var(--red-text);">
                                        <i class="bi bi-arrow-up"></i> R$ {{ number_format($fixed['difference'], 2, ',', '.') }} em despesa
                                    </span>
                                @elseif($fixed['difference'] < 0)
                                    <span class="card-badge" style="background: var(--green-soft); color: var(--green-text);">
                                        <i class="bi bi-arrow-down"></i> R$ {{ number_format(abs($fixed['difference']), 2, ',', '.') }} de economia
                                    </span>
                                @else
                                    <span class="card-badge" style="background: var(--green-soft); color: var(--green-text);">
                                        <i class="bi bi-check-lg"></i> Igual ao previsto
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Caixinhas: o que está no banco vs o que a API mostra --}}
    @if($import['investment_boxes'])
        <div class="section-title">
            Caixinhas — {{ $import['active_positions'] }}
            {{ $import['active_positions'] === 1 ? 'posição ativa' : 'posições ativas' }} na API
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Caixinha</th>
                        <th class="text-end" style="width:140px;">No sistema</th>
                        <th class="text-end" style="width:140px;">Na API</th>
                        <th class="text-end" style="width:180px;">Situação</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($import['investment_boxes'] as $box)
                        <tr>
                            <td>{{ $box['name'] }}</td>
                            <td class="text-end money">R$ {{ number_format($box['database'], 2, ',', '.') }}</td>
                            <td class="text-end money">R$ {{ number_format($box['api'], 2, ',', '.') }}</td>
                            <td class="text-end">
                                @if($box['matches'])
                                    <span class="card-badge" style="background: var(--green-soft); color: var(--green-text);">
                                        <i class="bi bi-check-lg"></i> Em dia
                                    </span>
                                @elseif($import['replaced_investments'])
                                    <span class="card-badge" style="background: var(--blue-soft); color: var(--blue-text);">
                                        <i class="bi bi-arrow-repeat"></i> Regravada
                                    </span>
                                @else
                                    <span class="card-badge" style="background: var(--yellow-soft); color: var(--yellow-text);">
                                        <i class="bi bi-exclamation-triangle"></i> Divergente
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- O aviso sem a ação obrigava a caçar a opção no modal: o botão abre lá
             já com ela marcada, faltando só a confirmação — que a regravação
             apaga as movimentações e continua sendo exigida. --}}
        @if(! $import['replaced_investments'] && collect($import['investment_boxes'])->contains('matches', false))
            <div class="card-body pt-3 d-flex align-items-center gap-3 flex-wrap">
                <button type="button"
                        class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-2"
                        data-bs-toggle="modal"
                        data-bs-target="#nubankImportModal"
                        data-import-open-replace>
                    <i class="bi bi-arrow-repeat"></i> Alinhar com a API
                </button>
                <span class="text-muted small">
                    Regrava as caixinhas com as posições reais — apaga o que não vier da API.
                </span>
            </div>
        @endif
    @endif

</div>
@endif
