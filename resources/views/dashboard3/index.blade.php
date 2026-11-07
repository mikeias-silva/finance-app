@extends('layouts.app')

@section('title', 'Dashboard 3')

@push('styles')
    <link href="https://fonts.googleapis.com/css2?family=Schibsted+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    @include('dashboard3._styles')
@endpush

@section('content')
<div class="d3" id="d3">
    <header class="d3-top">
        <h1 class="d3-title">Projeção financeira</h1>
        <div class="d3-actions">@include('partials.nubank-import-button')</div>
    </header>

    @include('partials.nubank-import-result')

    <section class="d3-stage" aria-label="Evolução do saldo em conta">
        <div class="d3-bar">
            <ul class="d3-legend" aria-label="Legenda do saldo">
                <li><span class="d3-swatch is-up" aria-hidden="true"></span>Patrimônio, acima de zero</li>
                <li><span class="d3-swatch is-down" aria-hidden="true"></span>Prejuízo acumulado, abaixo de zero</li>
            </ul>
            <label class="d3-horizon">
                <span>Ver <output id="d3-horizon-value">{{ $months }}</output> ciclos</span>
                <input type="range" id="d3-horizon" min="{{ $minMonths }}" max="{{ $maxMonths }}" step="1" value="{{ $months }}">
            </label>
        </div>

        <div class="d3-plots" id="d3-plots">
            <svg id="d3-tide" class="d3-svg" aria-hidden="true"></svg>
            <div class="d3-flow-head">
                <h2 class="d3-h">Entradas e saídas de cada ciclo</h2>
                <ul class="d3-legend" id="d3-flow-legend" aria-label="Legenda de entradas e saídas"></ul>
            </div>
            <svg id="d3-flow" class="d3-svg" aria-hidden="true"></svg>
            <div class="d3-tip" id="d3-tip" hidden></div>
            <input type="range" class="visually-hidden" id="d3-cursor" min="0" value="0" aria-label="Ciclo selecionado no gráfico">
        </div>
        <p class="d3-hint">Clique nos gráficos para escolher um ciclo. No celular, toque ou arraste o dedo. No teclado, use as setas.</p>
    </section>

    <section class="d3-cycle" id="d3-cycle" aria-labelledby="d3-cycle-title">
        <div class="d3-cycle-head">
            <div>
                <h2 class="d3-title" id="d3-cycle-title"></h2>
                <p class="d3-sub" id="d3-cycle-sub"></p>
            </div>
            <div class="d3-step">
                <button type="button" id="d3-prev" aria-label="Ciclo anterior"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
                <button type="button" id="d3-next" aria-label="Próximo ciclo"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
            </div>
        </div>

        <div class="d3-bridge" id="d3-bridge"></div>

        <div class="d3-share">
            <p class="d3-share-label" id="d3-share-label"></p>
            <div class="d3-share-bar" id="d3-share-bar" aria-hidden="true"></div>
        </div>

        <div class="d3-lists">
            <div>
                <h3 class="d3-h">Entradas</h3>
                <div id="d3-entries"></div>
            </div>
            <div>
                <h3 class="d3-h">Saídas</h3>
                <div id="d3-groups"></div>
            </div>
        </div>
    </section>

    @if($investmentData->isNotEmpty())
        <section class="d3-boxes" aria-labelledby="d3-boxes-title">
            <div class="d3-section-head">
                <h2 class="d3-title" id="d3-boxes-title">Caixinhas</h2>
                <a href="{{ route('investments.index') }}" class="d3-link">Abrir investimentos</a>
            </div>
            <dl class="d3-box-totals">
                <div><dt>Total investido</dt><dd><x-money :value="$totalInvested" /></dd></div>
                <div><dt>Rendimento acumulado</dt><dd><x-money :value="$totalYield" signed tone /></dd></div>
                <div><dt>Total com rendimento</dt><dd><x-money :value="$totalWithYield" /></dd></div>
            </dl>
            <div class="d3-box-list">
                @foreach($investmentData as $item)
                    @php
                        $box = $item['box'];
                        $used = $box->max_amount ? min(100, (int) round($item['balance'] / $box->max_amount * 100)) : null;
                    @endphp
                    <article class="d3-box" style="--d3-box: {{ $box->color ?: 'var(--d3-up)' }};">
                        <div class="d3-box-name">
                            <span class="d3-box-dot" aria-hidden="true"></span>
                            <strong>{{ $box->name }}</strong>
                            <span class="d3-muted">{{ number_format($box->rate_percentage, 0) }}% do CDI</span>
                        </div>
                        <dl class="d3-box-values">
                            <div><dt>Saldo</dt><dd><x-money :value="$item['balance']" /></dd></div>
                            <div><dt>Rendimento</dt><dd><x-money :value="$item['yield']" signed tone /></dd></div>
                            <div><dt>Total</dt><dd><x-money :value="$item['total']" /></dd></div>
                        </dl>
                        @if($used !== null)
                            <div class="d3-box-cap">
                                <span class="d3-box-track" aria-hidden="true"><span style="width: {{ $used }}%;"></span></span>
                                <span>Capacidade usada: {{ $used }}%, <x-money :value="$box->max_amount - $item['balance']" /> livre</span>
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    <section class="d3-table-wrap" aria-labelledby="d3-table-title">
        <h2 class="d3-title" id="d3-table-title">Todos os ciclos</h2>
        <div class="d3-table-scroll">
            <table class="d3-table">
                <thead>
                    <tr>
                        <th scope="col">Ciclo</th>
                        <th scope="col" class="is-num">Receitas</th>
                        <th scope="col" class="is-num">Despesas</th>
                        <th scope="col" class="is-num">Comprometido</th>
                        <th scope="col" class="is-num">Resultado do ciclo</th>
                        <th scope="col" class="is-num">Saldo em conta</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($cycles->values() as $index => $cycle)
                        @php $committed = $cycle['committed'] === null ? null : (int) round($cycle['committed'] * 100); @endphp
                        <tr data-index="{{ $index }}" @if($index >= $months) hidden @endif>
                            <th scope="row">
                                <button type="button" class="d3-row-link" data-select="{{ $index }}">{{ ucfirst($cycle['month']->translatedFormat('F \d\e Y')) }}</button>
                                @if($cycle['is_current'])<span class="d3-now">atual</span>@endif
                                <span class="d3-row-note">{{ $cycle['period_start']->format('d/m') }} a {{ $cycle['period_end']->format('d/m') }}</span>
                            </th>
                            <td class="is-num"><x-money :value="$cycle['income']" /></td>
                            <td class="is-num"><x-money :value="$cycle['expenses']" /></td>
                            <td class="is-num">
                                @if($committed === null)
                                    <span class="d3-muted" title="Ciclo sem receita">—</span>
                                @else
                                    <span @class(['negative' => $committed > 100])>{{ $committed }}%</span>
                                @endif
                            </td>
                            <td class="is-num"><x-money :value="$cycle['balance']" signed tone /></td>
                            <td class="is-num">
                                <x-money :value="$cycle['accumulated']" :class="$cycle['accumulated'] < 0 ? 'negative' : ''" />
                                @if($loop->first)
                                    <span class="d3-row-note">falta acontecer <x-money :value="$cycle['pending']" signed /></span>
                                @endif
                                @if($cycle['yield'] > 0)
                                    <span class="d3-row-note">rendimento <x-money :value="$cycle['yield']" signed /></span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</div>

{{-- JSON_HEX_TAG: uma descrição do extrato não consegue fechar este script. --}}
<script type="application/json" id="d3-data">{!! json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>
@endsection

@push('scripts')
    @include('dashboard3._script')
@endpush
