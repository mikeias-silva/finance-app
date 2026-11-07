<style>
    /* As cores de série passaram pelo validador de paleta nos dois temas. */
    .d3 {
        --d3-ink: #12262e;
        --d3-muted: #587079;
        --d3-hair: #d6e0e1;
        --d3-up: #0e7c8b;
        --d3-down: #cc3d38;
        --d3-up-text: #0b6673;
        --d3-down-text: #b02d29;
        --d3-warn: #8a5a00;
        --d3-s1: #2a78d6;
        --d3-s2: #eb6834;
        --d3-s3: #6250d6;
        --d3-s4: #eda100;
        --d3-s5: #7c8e95;
        --d3-free: #e3ebec;
        font-family: 'Schibsted Grotesk', 'DM Sans', system-ui, sans-serif;
        color: var(--d3-ink);
        max-width: 1180px;
        margin: 0 auto;
    }
    [data-bs-theme="dark"] .d3 {
        --d3-ink: #e4eff0;
        --d3-muted: #93a9b1;
        --d3-hair: #24383f;
        --d3-up: #3db6c6;
        --d3-down: #f0736a;
        --d3-up-text: #6fd0dc;
        --d3-down-text: #f59089;
        --d3-warn: #f0bf5e;
        --d3-s1: #3987e5;
        --d3-s2: #d95926;
        --d3-s3: #9085e9;
        --d3-s4: #c98500;
        --d3-s5: #8da2ab;
        --d3-free: #1c2f36;
    }

    .d3 .money { font-family: inherit; white-space: nowrap; }
    /* Números alinhados só em colunas: nos valores soltos, a vírgula tabular fica larga. */
    .d3-table .money, .d3-row .money, .d3-group summary .money { font-variant-numeric: tabular-nums; }
    .d3 .positive { color: var(--d3-up-text); }
    .d3 .negative { color: var(--d3-down-text); }
    .d3-muted { color: var(--d3-muted); }
    .d3 dl, .d3 dd, .d3 p, .d3 ul { margin: 0; padding: 0; }
    .d3 ul { list-style: none; }

    .d3-title { font-size: 1.375rem; font-weight: 600; letter-spacing: -.015em; line-height: 1.2; margin: 0; }
    .d3-h { font-size: .9375rem; font-weight: 600; margin: 0; }
    .d3-sub { font-size: .875rem; color: var(--d3-muted); margin-top: 2px; }
    .d3-link { font-size: .875rem; color: var(--d3-up-text); }

    /* ── Topo ── */
    .d3-top { display: flex; align-items: center; justify-content: space-between; gap: 12px 32px; flex-wrap: wrap; margin-bottom: 18px; }
    .d3-actions { flex: 0 0 auto; }

    /* ── Palco dos gráficos ── */
    .d3-bar { display: flex; align-items: center; justify-content: space-between; gap: 10px 24px; flex-wrap: wrap; margin-bottom: 6px; }
    .d3-legend { display: flex; flex-wrap: wrap; gap: 4px 16px; font-size: .8125rem; color: var(--d3-muted); }
    .d3-legend li { display: inline-flex; align-items: center; gap: 6px; }
    .d3-swatch { width: 10px; height: 10px; border-radius: 3px; background: var(--d3-up); flex: none; }
    .d3-swatch.is-down { background: var(--d3-down); }
    .d3-horizon { display: inline-flex; align-items: center; gap: 12px; font-size: .875rem; font-weight: 500; margin: 0; }
    .d3-horizon output { font-variant-numeric: tabular-nums; }
    .d3-horizon input { width: min(240px, 42vw); accent-color: var(--d3-up); cursor: ew-resize; }

    .d3-plots { position: relative; border-radius: 8px; }
    .d3-plots:has(#d3-cursor:focus-visible) { outline: 2px solid var(--d3-up); outline-offset: 4px; }
    .d3-svg { display: block; width: 100%; overflow: visible; touch-action: pan-y; cursor: pointer; user-select: none; }
    #d3-tide { height: clamp(280px, 44vh, 430px); }
    #d3-flow { height: 230px; }
    .d3-flow-head { display: flex; align-items: baseline; justify-content: space-between; gap: 4px 20px; flex-wrap: wrap; margin: 14px 0 2px; }
    .d3-hint { font-size: .8125rem; color: var(--d3-muted); margin-top: 8px !important; }

    .d3-grid { stroke: var(--d3-hair); stroke-width: 1; }
    .d3-zero { stroke: var(--d3-ink); stroke-width: 1.5; }
    .d3-axis { fill: var(--d3-muted); font-size: 11.5px; font-variant-numeric: tabular-nums; }
    .d3-axis.is-zero { fill: var(--d3-ink); font-weight: 600; }
    .d3-month { fill: var(--d3-muted); font-size: 12px; }
    .d3-month.is-sel { fill: var(--d3-ink); font-weight: 700; }
    .d3-month-year { fill: var(--d3-muted); font-size: 10.5px; }
    .d3-line-up { fill: none; stroke: var(--d3-up); stroke-width: 2.5; stroke-linejoin: round; }
    .d3-line-down { fill: none; stroke: var(--d3-down); stroke-width: 2.5; stroke-linejoin: round; }
    .d3-stop-up { stop-color: var(--d3-up); }
    .d3-stop-down { stop-color: var(--d3-down); }
    .d3-dot { stroke: var(--body-bg); stroke-width: 2; }
    .d3-dot.is-up { fill: var(--d3-up); }
    .d3-dot.is-down { fill: var(--d3-down); }
    .d3-dot.is-anchor { fill: var(--body-bg); stroke: var(--d3-muted); }
    .d3-label { font-size: 12.5px; font-weight: 600; fill: var(--d3-ink); paint-order: stroke; stroke: var(--body-bg); stroke-width: 4px; }
    .d3-label.is-down { fill: var(--d3-down-text); }
    .d3-label.is-note { font-weight: 400; font-size: 11.5px; fill: var(--d3-muted); }
    .d3-cursor { stroke: var(--d3-ink); stroke-width: 1; stroke-dasharray: 3 3; }
    .d3-col { fill: transparent; }
    .d3-col.is-sel { fill: var(--d3-free); }
    .d3-in { fill: var(--d3-up); }
    .d3-s-1 { fill: var(--d3-s1); background: var(--d3-s1); }
    .d3-s-2 { fill: var(--d3-s2); background: var(--d3-s2); }
    .d3-s-3 { fill: var(--d3-s3); background: var(--d3-s3); }
    .d3-s-4 { fill: var(--d3-s4); background: var(--d3-s4); }
    .d3-s-5 { fill: var(--d3-s5); background: var(--d3-s5); }

    .d3-reveal { animation: d3-reveal .9s cubic-bezier(.22, .8, .3, 1) both; }
    @keyframes d3-reveal { from { clip-path: inset(0 100% 0 0); } to { clip-path: inset(-20px -20px -20px 0); } }

    .d3-tip { position: absolute; top: 6px; left: 0; z-index: 3; width: 224px; padding: 10px 12px; background: var(--d3-ink); color: var(--body-bg); border-radius: 8px; font-size: .8125rem; line-height: 1.4; pointer-events: none; box-shadow: 0 8px 24px rgba(10, 30, 38, .22); }
    .d3-tip strong { display: block; font-weight: 600; margin-bottom: 4px; }
    .d3-tip div { display: flex; justify-content: space-between; gap: 12px; font-variant-numeric: tabular-nums; }
    .d3-tip span { white-space: nowrap; }
    .d3-tip span:first-child { opacity: .72; }

    /* ── Ciclo selecionado ── */
    .d3-cycle { margin-top: 26px; padding-top: 28px; border-top: 1px solid var(--d3-hair); scroll-margin-top: calc(var(--topbar-height) + 16px); }
    .d3-cycle-head { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
    .d3-step { display: inline-flex; gap: 6px; }
    .d3-step button { width: 36px; height: 36px; border: 1px solid var(--d3-hair); border-radius: 8px; background: transparent; color: var(--d3-ink); }
    .d3-step button:hover:not(:disabled) { border-color: var(--d3-muted); }
    .d3-step button:disabled { opacity: .35; }
    .d3-step button:focus-visible, .d3-row-link:focus-visible, .d3-group summary:focus-visible { outline: 2px solid var(--d3-up); outline-offset: 2px; }

    .d3-bridge { display: grid; grid-template-columns: repeat(var(--d3-steps, 4), minmax(0, 1fr)); margin-top: 18px; }
    .d3-bridge > div { position: relative; padding: 2px 18px 2px 0; }
    .d3-bridge > div + div { padding-left: 18px; border-left: 1px solid var(--d3-hair); }
    .d3-bridge > div > span { display: block; font-size: .8125rem; color: var(--d3-muted); }
    .d3-bridge strong { display: block; font-size: 1.375rem; font-weight: 600; letter-spacing: -.015em; }
    .d3-bridge small { display: block; font-size: .8125rem; color: var(--d3-muted); margin-top: 2px; }

    .d3-share { margin-top: 22px; }
    .d3-share-label { font-size: .875rem; font-weight: 500; margin-bottom: 6px !important; }
    .d3-share-bar { position: relative; display: flex; gap: 2px; height: 14px; }
    .d3-share-bar > span { height: 100%; min-width: 2px; }
    .d3-share-bar > span:first-child { border-radius: 4px 0 0 4px; }
    .d3-share-bar > span:last-of-type { border-radius: 0 4px 4px 0; }
    .d3-share-free { background: var(--d3-free); flex: 1 1 0; }
    .d3-share-limit { position: absolute; top: -3px; bottom: -3px; width: 2px; background: var(--d3-ink); }

    .d3-lists { display: grid; grid-template-columns: 5fr 7fr; gap: 24px 48px; margin-top: 26px; }
    .d3-row { display: flex; align-items: baseline; gap: 12px; padding: 8px 0; border-bottom: 1px solid var(--d3-hair); font-size: .9375rem; }
    .d3-row-date { flex: 0 0 44px; font-size: .8125rem; color: var(--d3-muted); font-variant-numeric: tabular-nums; }
    .d3-row-desc { flex: 1 1 auto; min-width: 0; overflow-wrap: anywhere; }
    .d3-row .money { flex: none; margin-left: auto; }
    .d3-row.is-total { font-weight: 600; border-bottom: 0; }
    .d3-tag { display: inline-block; margin-left: 4px; font-size: .75rem; color: var(--d3-muted); font-variant-numeric: tabular-nums; }
    .d3-tag.is-recurring { color: var(--d3-warn); }

    .d3-group { border-bottom: 1px solid var(--d3-hair); }
    .d3-group summary { display: grid; grid-template-columns: minmax(0, 1fr) auto auto; align-items: center; gap: 14px; padding: 9px 0; cursor: pointer; list-style: none; font-size: .9375rem; }
    .d3-group summary::-webkit-details-marker { display: none; }
    .d3-group-name { display: flex; align-items: center; gap: 8px; min-width: 0; font-weight: 500; }
    .d3-group-name b { font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .d3-group-name em { font-style: normal; font-weight: 400; font-size: .8125rem; color: var(--d3-muted); flex: none; }
    .d3-group-key { width: 10px; height: 10px; border-radius: 3px; flex: none; }
    .d3-chev { font-size: .75rem; color: var(--d3-muted); transition: transform .15s ease; }
    .d3-group[open] .d3-chev { transform: rotate(90deg); }
    .d3-pct { font-size: .8125rem; color: var(--d3-muted); font-variant-numeric: tabular-nums; }
    .d3-group-body { max-height: 24rem; overflow-y: auto; padding: 0 4px 8px 28px; }
    .d3-group-body .d3-row { font-size: .875rem; padding: 6px 0; }
    .d3-group-body .d3-row:last-child { border-bottom: 0; }

    /* ── Caixinhas ── */
    .d3-boxes, .d3-table-wrap { margin-top: 40px; padding-top: 30px; border-top: 1px solid var(--d3-hair); }
    .d3-section-head { display: flex; align-items: baseline; justify-content: space-between; gap: 16px; }
    .d3-box-totals { display: flex; flex-wrap: wrap; gap: 12px 44px; margin-top: 16px !important; }
    .d3-box-totals dt { font-size: .8125rem; font-weight: 500; color: var(--d3-muted); }
    .d3-box-totals dd { font-size: 1.25rem; font-weight: 600; letter-spacing: -.015em; }
    .d3-box-list { margin-top: 12px; }
    .d3-box { display: grid; grid-template-columns: minmax(180px, 1.2fr) minmax(0, 2fr) minmax(200px, 1.3fr); align-items: center; gap: 10px 28px; padding: 14px 0; border-top: 1px solid var(--d3-hair); }
    .d3-box-name { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; font-size: .9375rem; }
    .d3-box-name strong { font-weight: 600; }
    .d3-box-name .d3-muted { font-size: .8125rem; }
    .d3-box-dot { width: 10px; height: 10px; border-radius: 3px; background: var(--d3-box); }
    .d3-box-values { display: flex; gap: 8px 28px; flex-wrap: wrap; font-size: .9375rem; }
    .d3-box-values dt { font-size: .8125rem; font-weight: 400; color: var(--d3-muted); }
    .d3-box-cap { font-size: .8125rem; color: var(--d3-muted); }
    .d3-box-track { display: block; height: 6px; border-radius: 3px; background: var(--d3-free); overflow: hidden; margin-bottom: 5px; }
    .d3-box-track > span { display: block; height: 100%; background: var(--d3-box); border-radius: 3px; }

    /* ── Tabela ── */
    .d3-table-scroll { overflow-x: auto; margin-top: 10px; }
    .d3-table { width: 100%; min-width: 720px; border-collapse: collapse; font-size: .9375rem; }
    .d3-table thead th { font-size: .8125rem; font-weight: 500; color: var(--d3-muted); padding: 8px 12px; border-bottom: 1px solid var(--d3-hair); white-space: nowrap; }
    .d3-table tbody th, .d3-table td { padding: 11px 12px; border-bottom: 1px solid var(--d3-hair); vertical-align: top; font-weight: 400; }
    .d3-table th:first-child { padding-left: 8px; }
    .d3-table .is-num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .d3-table tbody tr { cursor: pointer; }
    .d3-table tbody tr:hover, .d3-table tbody tr.is-sel { background: var(--d3-free); }
    .d3-row-link { border: 0; padding: 0; background: none; color: inherit; font-weight: 500; }
    .d3-row-note { display: block; font-size: .8125rem; color: var(--d3-muted); }
    .d3-now { font-size: .75rem; font-weight: 600; color: var(--d3-up-text); margin-left: 6px; }

    @media (max-width: 991.98px) {
        .d3-lists { grid-template-columns: 1fr; }
        .d3-box { grid-template-columns: 1fr; }
    }
    @media (max-width: 575.98px) {
        .d3 { padding: 0 16px; }
        .d3-bridge { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px 0; }
        .d3-bridge > div:nth-child(odd) { padding-left: 0; border-left: 0; }
        .d3-bridge strong { font-size: 1.0625rem; }
        .d3-horizon { width: 100%; justify-content: space-between; }
        .d3-horizon input { width: 60%; }
    }
    @media (prefers-reduced-motion: reduce) {
        .d3-reveal { animation: none; }
        .d3-chev { transition: none; }
    }
    @media (forced-colors: active) {
        .d3-share-bar > span, .d3-swatch, .d3-group-key { forced-color-adjust: none; }
    }
</style>
