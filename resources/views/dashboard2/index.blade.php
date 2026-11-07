@extends('layouts.app')

@section('title', 'Dashboard 2')

@push('styles')
<style>
    /* Só tokens do layout: o tema escuro vem de graça. */
    .d2 .money, .d2 .num { white-space: nowrap; }
    .d2 .num { font-family: 'DM Mono', monospace; font-variant-numeric: tabular-nums; }
    .d2 .positive { color: var(--green-text); }
    .d2 .negative { color: var(--red-text); }
    .d2 .muted { color: var(--text-muted); }

    /* ── Cabeçalho ── */
    .d2-header { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 20px; }
    .d2-title { font-size: 20px; font-weight: 600; letter-spacing: -.3px; margin: 0; }
    .d2-subtitle { font-size: 14px; color: var(--text-muted); margin: 4px 0 0; }
    .d2-controls { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    .d2-controls-label { font-size: 13px; color: var(--text-muted); }

    /* ── Seções ── */
    .d2-section { background: var(--surface); border: 1px solid var(--card-border); border-radius: 12px; box-shadow: var(--shadow); }
    .d2-section + .d2-section { margin-top: 24px; }
    .d2-section-head { display: flex; align-items: baseline; justify-content: space-between; gap: 6px 16px; flex-wrap: wrap; padding: 18px 20px 4px; }
    .d2-section-title { font-size: 16px; font-weight: 600; margin: 0; }
    .d2-section-note { font-size: 13px; color: var(--text-muted); }

    /* ── Números principais: o vão de 1px do grid vira a linha divisória em qualquer quebra ── */
    .d2-figures { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1px; background: var(--card-border); border: 1px solid var(--card-border); border-radius: 10px; overflow: hidden; margin: 12px 20px 4px; }
    .d2-figures-3 { grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); }
    .d2-figure { background: var(--surface); padding: 14px 16px; min-width: 0; }
    .d2-figure-label { font-size: 13px; font-weight: 500; color: var(--text-muted); }
    .d2-figure-value { font-size: 24px; font-weight: 600; letter-spacing: -.4px; line-height: 1.2; margin-top: 2px; }
    .d2-figure-note { font-size: 12.5px; color: var(--text-muted); margin-top: 6px; line-height: 1.5; }
    .d2-stale { color: var(--yellow-text); }

    /* ── Gráfico de linha ── */
    .d2-line { padding: 14px 20px 6px; }
    /* O respiro no alto evita cortar o rótulo da linha de escala do topo. */
    .d2-line-scroll { overflow-x: auto; padding-top: 10px; }
    .d2-line-inner { display: grid; grid-template-columns: 52px minmax(calc(var(--d2-n) * 60px), 1fr); grid-template-rows: 240px auto; }
    .d2-line-y { position: relative; }
    .d2-line-y span { position: absolute; right: 10px; transform: translateY(-50%); font-size: 11.5px; color: var(--text-muted); white-space: nowrap; font-variant-numeric: tabular-nums; }
    /* As faixas clicáveis das pontas passam da borda: cortadas aqui, não geram rolagem. */
    .d2-line-plot { position: relative; overflow: hidden; }
    .d2-grid { position: absolute; left: 0; right: 0; border-top: 1px solid var(--surface-alt); }
    .d2-grid.is-zero { border-top-color: var(--input-focus); }
    /* Pontos, traço e rótulos dividem a mesma caixa, recuada para os rótulos das pontas caberem. */
    .d2-line-area, .d2-line-x-inner { position: absolute; top: 0; bottom: 0; left: 40px; right: 40px; }
    .d2-line-area svg { position: absolute; inset: 0; width: 100%; height: 100%; overflow: visible; }
    .d2-hit { position: absolute; top: 0; bottom: 0; transform: translateX(-50%); border: 0; padding: 0; background: transparent; cursor: pointer; }
    .d2-hit::before { content: ''; position: absolute; left: 50%; top: 0; bottom: 0; border-left: 1px solid var(--input-focus); opacity: 0; }
    .d2-hit:hover::before, .d2-hit:focus-visible::before, .d2-hit.is-selected::before { opacity: .6; }
    .d2-hit:focus-visible { outline: none; }
    .d2-point { position: absolute; width: 10px; height: 10px; border-radius: 50%; transform: translate(-50%, -50%); background: var(--green); box-shadow: 0 0 0 2px var(--surface); pointer-events: none; transition: transform .15s ease; }
    .d2-point.negative { background: var(--red); }
    .d2-point.is-anchor { background: var(--surface); border: 2px solid var(--text-muted); }
    .d2-point.is-current { width: 12px; height: 12px; }
    .d2-hit:hover + .d2-point, .d2-hit:focus-visible + .d2-point, .d2-point.is-selected { transform: translate(-50%, -50%) scale(1.35); }
    .d2-point-label { position: absolute; font-size: 12px; font-weight: 500; line-height: 1; white-space: nowrap; color: var(--text-primary); pointer-events: none; font-variant-numeric: tabular-nums; }
    .d2-point-label.at-top { transform: translate(-50%, calc(-100% - 10px)); }
    .d2-point-label.at-bottom { transform: translate(-50%, 10px); }
    .d2-point-label.at-top-left { transform: translate(calc(-100% - 7px), calc(-100% - 7px)); }
    .d2-point-label.at-top-right { transform: translate(7px, calc(-100% - 7px)); }
    .d2-point-label.at-bottom-left { transform: translate(calc(-100% - 7px), 7px); }
    .d2-point-label.at-bottom-right { transform: translate(7px, 7px); }
    .d2-point-label.is-negative { color: var(--red-text); }
    .d2-point-label.is-anchor { color: var(--text-muted); }
    .d2-point-label.is-strong { font-weight: 700; }
    .d2-line-x { position: relative; grid-column: 2; height: 44px; }
    .d2-x { position: absolute; top: 10px; transform: translateX(-50%); text-align: center; font-size: 12.5px; line-height: 1.25; white-space: nowrap; color: var(--text-primary); }
    .d2-x small { display: block; font-size: 11px; color: var(--text-muted); }
    .d2-x.is-anchor { color: var(--text-muted); }
    .d2-x.is-current small { color: var(--blue-text); font-weight: 600; }
    .d2-x.is-selected { font-weight: 600; }

    /* ── Tabela de ciclos ── */
    .d2-cycles { --bs-table-bg: transparent; --bs-table-color: var(--text-primary); --bs-table-border-color: var(--surface-alt); margin-top: 8px; }
    .d2-cycles thead th { text-transform: none; letter-spacing: 0; font-size: 12.5px; font-weight: 500; color: var(--text-muted); white-space: nowrap; padding: 10px 12px; border-top: 1px solid var(--card-border); border-bottom-color: var(--card-border); }
    .d2-cycles td { padding: 12px; vertical-align: top; white-space: nowrap; }
    .d2-cycles th:first-child, .d2-cycles td:first-child { padding-left: 20px; }
    .d2-cycles th:last-child, .d2-cycles td:last-child { padding-right: 20px; }
    .d2-cycles tbody tr { cursor: pointer; }
    .d2-cycles tbody tr:last-child td { border-bottom: 0; }
    .d2-cycles tbody tr.is-selected > * { --bs-table-bg-state: var(--surface-alt); }
    .d2-cycle-link { border: 0; background: none; padding: 0; color: var(--text-primary); font-weight: 500; text-align: left; }
    .d2-cycle-link:hover { text-decoration: underline; }
    .d2-cycle-link:focus-visible { outline: 2px solid var(--blue); outline-offset: 2px; border-radius: 2px; }
    .d2-cell-note { font-size: 12px; color: var(--text-muted); font-family: 'DM Sans', sans-serif; margin-top: 2px; }
    .d2-pill { display: inline-block; font-size: 11px; font-weight: 600; line-height: 18px; padding: 0 8px; border-radius: 999px; background: var(--blue-soft); color: var(--blue-text); margin-left: 6px; vertical-align: 1px; }
    .d2-committed { display: inline-flex; align-items: center; gap: 8px; }

    /* Medidor: trilho na cor da borda para não sumir sobre a linha selecionada. */
    .d2-meter { display: inline-block; width: 64px; height: 6px; border-radius: 999px; background: var(--card-border); overflow: hidden; vertical-align: middle; flex-shrink: 0; }
    .d2-meter > span { display: block; height: 100%; background: var(--blue); border-radius: inherit; }
    .d2-meter.is-over > span { background: var(--red); }
    .d2-meter-sm { width: 44px; height: 5px; }
    .d2-meter-full { display: block; width: 100%; height: 5px; margin-top: 6px; }

    /* ── Detalhe do ciclo ── */
    /* Ao rolar até aqui, para abaixo da barra superior fixa em vez de sumir sob ela. */
    #d2-detail { scroll-margin-top: calc(var(--topbar-height) + 16px); }
    .d2-tabs { flex-wrap: nowrap; gap: 6px; overflow-x: auto; padding: 10px 20px 2px; }
    .d2-tabs .d2-tab { flex: 0 0 auto; border: 1px solid var(--card-border); background: transparent; color: var(--text-muted); border-radius: 999px; padding: 5px 14px; font-size: 13px; font-weight: 500; }
    .d2-tabs .d2-tab:hover { color: var(--text-primary); border-color: var(--input-focus); }
    .d2-tabs .d2-tab.active { background: var(--btn-primary-bg); border-color: var(--btn-primary-bg); color: #fff; }
    .d2-tabs .d2-tab:focus-visible { outline: 2px solid var(--blue); outline-offset: 2px; }
    .d2-pane { padding: 16px 20px 20px; }
    .d2-pane:focus-visible { outline: none; }
    .d2-cycle-head { display: flex; align-items: baseline; gap: 4px 12px; flex-wrap: wrap; font-size: 13px; }
    .d2-cycle-title { font-size: 18px; font-weight: 600; margin: 0; }
    .d2-facts { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1px; background: var(--card-border); border: 1px solid var(--card-border); border-radius: 10px; overflow: hidden; margin: 14px 0 22px; }
    .d2-facts > div { background: var(--surface); padding: 12px 14px; min-width: 0; }
    .d2-facts dt { font-size: 12.5px; font-weight: 500; color: var(--text-muted); }
    .d2-facts dd { margin: 2px 0 0; font-size: 18px; font-weight: 600; }
    .d2-facts dd.d2-fact-note { font-size: 12.5px; font-weight: 400; color: var(--text-muted); margin-top: 4px; }
    .d2-list-title { font-size: 14px; font-weight: 600; margin: 0 0 4px; }
    .d2-row { display: flex; align-items: baseline; gap: 12px; padding: 8px 0; border-bottom: 1px solid var(--surface-alt); font-size: 14px; }
    .d2-row > .money { margin-left: auto; flex-shrink: 0; }
    .d2-row-date { flex: 0 0 44px; font-size: 12.5px; color: var(--text-muted); }
    .d2-row-desc { flex: 1 1 auto; min-width: 0; overflow-wrap: anywhere; }
    .d2-row-total { border-bottom: 0; font-weight: 600; }
    .d2-tag { display: inline-block; margin-left: 4px; font-size: 11px; line-height: 17px; padding: 0 7px; border-radius: 999px; background: var(--surface-alt); color: var(--text-muted); white-space: nowrap; vertical-align: 1px; }
    .d2-tag.is-recurring { background: var(--yellow-soft); color: var(--yellow-text); }
    .d2-dot { display: inline-block; width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0; }

    .d2-group { border-bottom: 1px solid var(--surface-alt); }
    .d2-group > summary { list-style: none; display: grid; grid-template-columns: minmax(0, 1fr) auto auto; align-items: center; gap: 12px; padding: 10px 0; cursor: pointer; font-size: 14px; }
    .d2-group > summary::-webkit-details-marker { display: none; }
    .d2-group > summary:focus-visible { outline: 2px solid var(--blue); outline-offset: 2px; border-radius: 4px; }
    .d2-group-name { display: flex; align-items: center; gap: 8px; min-width: 0; font-weight: 500; }
    .d2-group-name > span:not(.d2-dot) { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .d2-group-count { font-weight: 400; font-size: 12.5px; flex-shrink: 0; }
    .d2-chevron { font-size: 12px; color: var(--text-muted); transition: transform .15s ease; }
    .d2-group[open] .d2-chevron { transform: rotate(90deg); }
    .d2-share { display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; color: var(--text-muted); }
    /* Fatura com dezenas de itens rola por dentro, como o modal de antes, em vez de esticar a página. */
    .d2-group-body { padding: 0 6px 10px 22px; max-height: 26rem; overflow-y: auto; }
    .d2-group-body .d2-row { font-size: 13.5px; padding: 6px 0; }
    .d2-group-body .d2-row:last-child { border-bottom: 0; }

    /* ── Caixinhas ── */
    .d2-boxes { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 290px), 1fr)); gap: 12px; padding: 12px 20px 20px; }
    .d2-box { border: 1px solid var(--card-border); border-left: 4px solid var(--d2-box-color); border-radius: 10px; padding: 14px 16px; }
    .d2-box .d2-dot { background: var(--d2-box-color); margin-right: 8px; }
    .d2-box-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
    .d2-box-name { font-size: 14px; font-weight: 500; display: inline-flex; align-items: center; }
    .d2-box-values { margin: 10px 0 0; }
    .d2-box-values > div { display: flex; justify-content: space-between; gap: 12px; padding: 3px 0; font-size: 13.5px; }
    .d2-box-values dt { font-weight: 400; color: var(--text-muted); }
    .d2-box-values dd { margin: 0; }
    .d2-box-capacity { margin-top: 10px; font-size: 12px; color: var(--text-muted); }
    .d2-box-capacity .d2-meter > span { background: var(--d2-box-color); }

    @media (max-width: 991.98px) {
        .d2-figures { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .d2-figures-3 { grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); }
    }
    @media (max-width: 767.98px) {
        .d2-facts { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 575.98px) {
        .d2-figures { margin-left: 16px; margin-right: 16px; }
        .d2-figure { padding: 12px; }
        .d2-figure-value { font-size: 19px; }
        .d2-section-head, .d2-pane { padding-left: 16px; padding-right: 16px; }
        .d2-line { padding-left: 8px; padding-right: 12px; }
        .d2-line-inner { grid-template-columns: 54px minmax(calc(var(--d2-n) * 36px), 1fr); grid-template-rows: 210px auto; }
        .d2-line-y span { right: 6px; font-size: 11px; }
        .d2-line-area, .d2-line-x-inner { left: 28px; right: 28px; }
        .d2-point-label.is-minor, .d2-x-long { display: none; }

        /* A tabela vira um cartão por ciclo: sem rolagem lateral escondendo o saldo. */
        .d2-cycles thead { display: none; }
        .d2-cycles, .d2-cycles tbody { display: block; }
        .d2-cycles tbody tr {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            grid-template-areas: "cycle balance" "income expenses" "committed result";
            gap: 8px 12px;
            padding: 12px 16px;
            border-bottom: 1px solid var(--surface-alt);
        }
        .d2-cycles tbody tr:last-child { border-bottom: 0; }
        .d2-cycles tbody tr.is-selected { background: var(--surface-alt); }
        .d2-cycles td { display: block; padding: 0 !important; border: 0; white-space: normal; }
        .d2-cycles td[data-label]::before { content: attr(data-label); display: block; font-family: 'DM Sans', sans-serif; font-size: 11.5px; color: var(--text-muted); }
        .d2-cycles td.d2-c-cycle { grid-area: cycle; }
        .d2-cycles td.d2-c-balance { grid-area: balance; }
        .d2-cycles td.d2-c-income { grid-area: income; text-align: left !important; }
        .d2-cycles td.d2-c-expenses { grid-area: expenses; }
        .d2-cycles td.d2-c-committed { grid-area: committed; }
        .d2-cycles td.d2-c-result { grid-area: result; }

        .d2-figures-3 { grid-template-columns: 1fr 1fr; }
        .d2-figures-3 .d2-figure:last-child { grid-column: 1 / -1; }
        .d2-share .d2-meter { display: none; }
        .d2-facts dd { font-size: 16px; }
    }
    @media (prefers-reduced-motion: reduce) {
        .d2-chevron, .d2-point { transition: none; }
    }
</style>
@endpush

@section('content')
@php
    $firstMonth = $cycles->first()['month'];
    $lastMonth = $cycles->last()['month'];
@endphp

<div class="d2">
    <div class="d2-header">
        <div>
            <h1 class="d2-title">Projeção financeira</h1>
            <p class="d2-subtitle">
                @if($cycles->count() === 1)
                    Ciclo de {{ $firstMonth->translatedFormat('F \d\e Y') }}
                @else
                    Ciclos de {{ $firstMonth->translatedFormat('F \d\e Y') }} a {{ $lastMonth->translatedFormat('F \d\e Y') }}
                @endif
            </p>
        </div>
        <div class="d2-controls">
            @include('partials.nubank-import-button')

            <span class="d2-controls-label" id="d2-horizon-label">Horizonte</span>
            <div class="btn-group btn-group-sm" role="group" aria-labelledby="d2-horizon-label">
                @foreach([3, 6, 12] as $m)
                    <a href="{{ route('dashboard2', ['months' => $m]) }}"
                       class="btn {{ $months == $m ? 'btn-primary' : 'btn-outline-secondary' }}"
                       @if($months == $m) aria-current="true" @endif>
                        {{ $m }}m
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    @include('partials.nubank-import-result')

    <section class="d2-section" aria-labelledby="d2-balance-title">
        <div class="d2-section-head">
            <h2 class="d2-section-title" id="d2-balance-title">Saldo em conta mês a mês</h2>
            <span class="d2-section-note">Conta corrente e caixinhas no fim de cada ciclo, antes do próximo salário</span>
        </div>

        @include('dashboard2._figures')
        @include('dashboard2._chart')
        @include('dashboard2._cycles')
    </section>

    @include('dashboard2._detail')
    @include('dashboard2._investments')
</div>
@endsection

@push('scripts')
<script>
    (() => {
        // Gráfico, tabela e abas falam do mesmo ciclo: escolher num lugar marca
        // nos três e leva até o detalhe.
        const root = document.querySelector('.d2');
        const detail = document.getElementById('d2-detail');
        if (!root || !detail) return;

        const smooth = !window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        function mark(key) {
            root.querySelectorAll('[data-cycle]').forEach((el) => {
                el.classList.toggle('is-selected', el.dataset.cycle === key);
            });
        }

        function open(key) {
            const tab = root.querySelector(`[data-cycle-tab="${key}"]`);
            if (!tab) return;

            bootstrap.Tab.getOrCreateInstance(tab).show();
            detail.scrollIntoView({ behavior: smooth ? 'smooth' : 'auto', block: 'start' });
        }

        root.querySelectorAll('[data-cycle-tab]').forEach((tab) => {
            tab.addEventListener('shown.bs.tab', () => mark(tab.dataset.cycleTab));
        });

        root.querySelectorAll('[data-cycle-open]').forEach((trigger) => {
            trigger.addEventListener('click', (event) => {
                event.stopPropagation();
                open(trigger.dataset.cycleOpen);
            });
        });

        // A linha inteira da tabela abre o ciclo; o botão do nome continua sendo
        // o caminho pelo teclado.
        root.querySelectorAll('tr[data-cycle]').forEach((row) => {
            row.addEventListener('click', () => open(row.dataset.cycle));
        });

        const active = root.querySelector('[data-cycle-tab].active');
        if (active) mark(active.dataset.cycleTab);
    })();
</script>
@endpush
