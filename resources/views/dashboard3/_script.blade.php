<script>
(() => {
    const dataEl = document.getElementById('d3-data');
    if (!dataEl) return;

    const data = JSON.parse(dataEl.textContent);
    const cycles = data.cycles;
    const anchor = data.anchor;
    const seriesById = Object.fromEntries(data.series.map((s) => [s.id, s]));
    const NS = 'http://www.w3.org/2000/svg';
    const byId = (id) => document.getElementById(id);

    const plots = byId('d3-plots');
    const tide = byId('d3-tide');
    const flow = byId('d3-flow');
    const tip = byId('d3-tip');
    const cursor = byId('d3-cursor');
    const horizon = byId('d3-horizon');

    let months = Math.min(data.months, cycles.length);
    let selected = 0;
    let revealed = false;
    let geometry = null;

    /* ── Formatação ── */
    const MINUS = '−';
    const number = (value, digits) => Math.abs(value).toLocaleString('pt-BR', { minimumFractionDigits: digits, maximumFractionDigits: digits });
    const money = (value, signed = false) => {
        const rounded = Math.round(value * 100) / 100;
        const sign = rounded < 0 ? MINUS : (signed && rounded > 0 ? '+' : '');
        return `${sign}R$ ${number(rounded, 2)}`;
    };
    const whole = (value) => `${value < 0 ? MINUS : ''}R$ ${number(Math.round(value), 0)}`;
    const axis = (value) => {
        if (value === 0) return 'R$ 0';
        const thousands = Math.abs(value) / 1000;
        const digits = Number.isInteger(thousands) ? 0 : 1;
        return `${value < 0 ? MINUS : ''}R$ ${thousands.toLocaleString('pt-BR', { maximumFractionDigits: digits })} mil`;
    };

    /* ── DOM ── */
    function el(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    }
    function moneyEl(value, { signed = false, tone = false } = {}) {
        const classes = ['money'];
        if (tone && value > 0) classes.push('positive');
        if ((tone || !signed) && value < 0) classes.push('negative');
        return el('span', classes.join(' '), money(value, signed));
    }
    function svg(tag, attrs = {}, parent, text) {
        const node = document.createElementNS(NS, tag);
        Object.entries(attrs).forEach(([key, value]) => node.setAttribute(key, value));
        if (text !== undefined) node.textContent = text;
        if (parent) parent.appendChild(node);
        return node;
    }
    function fill(node, ...children) {
        node.replaceChildren(...children);
    }

    /* ── Escala ── */
    function niceScale(low, high, padding = 0.14, bands = 4) {
        const range = (high - low) || 1000;
        const rough = range / bands;
        const power = 10 ** Math.floor(Math.log10(rough));
        const step = [1, 2, 2.5, 5, 10].map((m) => m * power).find((s) => s >= rough);
        const top = Math.ceil((high + range * padding) / step) * step;
        const bottom = low < 0 ? Math.floor((low - range * padding) / step) * step : 0;
        const ticks = [];
        for (let tick = bottom; tick <= top + step / 2; tick += step) ticks.push(Math.round(tick));
        return { top, bottom, ticks };
    }

    /* Curva monotônica: passa por todos os pontos sem inventar pico entre eles. */
    function monotonePath(points) {
        if (points.length < 2) return '';
        const n = points.length;
        const dx = [], slope = [];
        for (let i = 0; i < n - 1; i++) {
            dx[i] = points[i + 1].x - points[i].x;
            slope[i] = (points[i + 1].y - points[i].y) / dx[i];
        }
        const tangent = [slope[0]];
        for (let i = 1; i < n - 1; i++) {
            tangent[i] = slope[i - 1] * slope[i] <= 0 ? 0 : (slope[i - 1] + slope[i]) / 2;
        }
        tangent[n - 1] = slope[n - 2];
        let d = `M${points[0].x},${points[0].y}`;
        for (let i = 0; i < n - 1; i++) {
            const h = dx[i] / 3;
            d += `C${points[i].x + h},${points[i].y + tangent[i] * h} ${points[i + 1].x - h},${points[i + 1].y - tangent[i + 1] * h} ${points[i + 1].x},${points[i + 1].y}`;
        }
        return d;
    }

    function visible() {
        return cycles.slice(0, months);
    }

    /* ── Gráfico do saldo ── */
    function drawTide() {
        const width = plots.clientWidth;
        const height = tide.clientHeight;
        const narrow = width < 560;
        const pad = { left: narrow ? 50 : 62, right: narrow ? 16 : 34, top: 28, bottom: 34 };
        const list = visible();
        const values = [anchor.value, ...list.map((c) => c.accumulated)];
        const { top, bottom, ticks } = niceScale(Math.min(0, ...values), Math.max(0, ...values));

        const plotWidth = width - pad.left - pad.right;
        const x = (i) => pad.left + (i / (values.length - 1)) * plotWidth;
        const y = (value) => pad.top + ((top - value) / (top - bottom)) * (height - pad.top - pad.bottom);
        const zero = y(0);
        const points = values.map((value, i) => ({ x: x(i), y: y(value) }));
        const line = monotonePath(points);
        const area = `${line}L${points[points.length - 1].x},${zero}L${points[0].x},${zero}Z`;

        geometry = { x, width, narrow, pad };

        tide.setAttribute('viewBox', `0 0 ${width} ${height}`);
        tide.replaceChildren();

        const defs = svg('defs', {}, tide);
        const above = svg('clipPath', { id: 'd3-above' }, defs);
        svg('rect', { x: 0, y: -20, width, height: Math.max(0, zero + 20) }, above);
        const below = svg('clipPath', { id: 'd3-below' }, defs);
        svg('rect', { x: 0, y: zero, width, height: Math.max(0, height - zero + 20) }, below);

        const upGradient = svg('linearGradient', { id: 'd3-up-fill', gradientUnits: 'userSpaceOnUse', x1: 0, x2: 0, y1: y(top), y2: zero }, defs);
        svg('stop', { offset: 0, class: 'd3-stop-up', 'stop-opacity': .42 }, upGradient);
        svg('stop', { offset: 1, class: 'd3-stop-up', 'stop-opacity': .06 }, upGradient);
        const downGradient = svg('linearGradient', { id: 'd3-down-fill', gradientUnits: 'userSpaceOnUse', x1: 0, x2: 0, y1: zero, y2: y(bottom) }, defs);
        svg('stop', { offset: 0, class: 'd3-stop-down', 'stop-opacity': .1 }, downGradient);
        svg('stop', { offset: 1, class: 'd3-stop-down', 'stop-opacity': .55 }, downGradient);

        ticks.forEach((tick) => {
            if (tick !== 0) svg('line', { class: 'd3-grid', x1: pad.left, x2: width - pad.right, y1: y(tick), y2: y(tick) }, tide);
            svg('text', { class: `d3-axis${tick === 0 ? ' is-zero' : ''}`, x: pad.left - 10, y: y(tick) + 4, 'text-anchor': 'end' }, tide, axis(tick));
        });

        const chart = svg('g', { class: revealed ? '' : 'd3-reveal' }, tide);
        svg('path', { d: area, fill: 'url(#d3-up-fill)', 'clip-path': 'url(#d3-above)' }, chart);
        svg('path', { d: area, fill: 'url(#d3-down-fill)', 'clip-path': 'url(#d3-below)' }, chart);
        svg('line', { class: 'd3-zero', x1: pad.left, x2: width - pad.right, y1: zero, y2: zero }, tide);
        svg('path', { d: line, class: 'd3-line-up', 'clip-path': 'url(#d3-above)' }, chart);
        svg('path', { d: line, class: 'd3-line-down', 'clip-path': 'url(#d3-below)' }, chart);

        const every = monthEvery(width, values.length);
        list.forEach((cycle, i) => {
            if ((i + 1) % every && i !== selected) return;
            const isSelected = i === selected;
            svg('text', { class: `d3-month${isSelected ? ' is-sel' : ''}`, x: x(i + 1), y: height - 16, 'text-anchor': 'middle' }, tide, cycle.short);
            if (cycle.short === 'Jan' || i === 0) {
                svg('text', { class: 'd3-month-year', x: x(i + 1), y: height - 3, 'text-anchor': 'middle' }, tide, cycle.year);
            }
        });
        svg('text', { class: 'd3-month', x: x(0), y: height - 16, 'text-anchor': 'middle' }, tide, 'Hoje');

        const lowestIndex = values.indexOf(Math.min(...values));
        const lastIndex = values.length - 1;
        const dotRadius = list.length > 12 ? 2.5 : 3.5;

        values.forEach((value, i) => {
            const isKey = i === 0 || i === lowestIndex || i === lastIndex;
            svg('circle', {
                class: `d3-dot ${i === 0 ? 'is-anchor' : (value < 0 ? 'is-down' : 'is-up')}`,
                cx: points[i].x,
                cy: points[i].y,
                r: isKey ? 5 : dotRadius,
            }, chart);
        });

        const label = (i, text, note) => {
            const value = values[i];
            const anchorX = i === 0 ? 'start' : (i === lastIndex ? 'end' : 'middle');
            const upward = i === lowestIndex ? value >= 0 : true;
            const base = points[i].y + (upward ? -12 : 20);
            svg('text', { class: `d3-label${value < 0 ? ' is-down' : ''}`, x: points[i].x, y: base, 'text-anchor': anchorX }, chart, text);
            if (note) svg('text', { class: 'd3-label is-note', x: points[i].x, y: base + (upward ? -15 : 15), 'text-anchor': anchorX }, chart, note);
        };
        label(0, whole(values[0]), anchor.readShort ? `lido em ${anchor.readShort}` : 'estimado');
        if (lowestIndex !== 0 && lowestIndex !== lastIndex) label(lowestIndex, whole(values[lowestIndex]), 'menor saldo');
        if (lastIndex !== 0) label(lastIndex, whole(values[lastIndex]), lowestIndex === lastIndex ? 'menor saldo' : 'fim do horizonte');

        const sx = x(selected + 1);
        svg('line', { class: 'd3-cursor', x1: sx, x2: sx, y1: pad.top - 12, y2: height - pad.bottom + 6 }, tide);
        const cycle = list[selected];
        svg('circle', { class: `d3-dot ${cycle.accumulated < 0 ? 'is-down' : 'is-up'}`, cx: sx, cy: y(cycle.accumulated), r: 7 }, tide);

        revealed = true;
    }

    function monthEvery(width, count) {
        const room = (width - 80) / count;
        return room >= 44 ? 1 : (room >= 22 ? 2 : (room >= 14 ? 3 : 4));
    }

    /* ── Gráfico de entradas e saídas: mesma régua de meses do saldo ── */
    function drawFlow() {
        const width = plots.clientWidth;
        const height = flow.clientHeight;
        const { x, pad } = geometry;
        const list = visible();
        const top = Math.max(1, ...list.map((c) => c.income));
        const low = Math.max(1, ...list.map((c) => c.expenses));
        const scale = niceScale(-low, top, 0.01, 3);
        const y = (value) => 6 + ((scale.top - value) / (scale.top - scale.bottom)) * (height - 12);
        const zero = y(0);
        const step = list.length > 1 ? x(2) - x(1) : 120;
        const barWidth = Math.max(3, Math.min(30, step * 0.5));

        flow.setAttribute('viewBox', `0 0 ${width} ${height}`);
        flow.replaceChildren();

        scale.ticks.forEach((tick) => {
            if (tick !== 0) svg('line', { class: 'd3-grid', x1: pad.left, x2: width - pad.right, y1: y(tick), y2: y(tick) }, flow);
            svg('text', { class: `d3-axis${tick === 0 ? ' is-zero' : ''}`, x: pad.left - 10, y: y(tick) + 4, 'text-anchor': 'end' }, flow, axis(tick));
        });

        svg('rect', { class: 'd3-col is-sel', x: x(selected + 1) - step / 2, y: 0, width: step, height, rx: 6 }, flow).style.opacity = .7;

        const radius = Math.min(4, barWidth / 2);
        list.forEach((cycle, i) => {
            const cx = x(i + 1);
            const left = cx - barWidth / 2;

            if (cycle.income > 0) {
                const yTop = y(cycle.income);
                svg('path', { class: 'd3-in', d: `M${left},${zero - 1}V${yTop + radius}Q${left},${yTop} ${left + radius},${yTop}H${left + barWidth - radius}Q${left + barWidth},${yTop} ${left + barWidth},${yTop + radius}V${zero - 1}Z` }, flow);
            }

            let cursorY = zero + 1;
            const parts = stack(cycle).filter((part) => part.total > 0);
            parts.forEach((part, j) => {
                const h = Math.max(1, y(0 - part.total) - zero - 2);
                const isLast = j === parts.length - 1;
                const r = isLast ? Math.min(radius, h) : 0;
                const bottomY = cursorY + h;
                svg('path', {
                    class: `d3-s-${part.slot}`,
                    d: `M${left},${cursorY}H${left + barWidth}V${bottomY - r}Q${left + barWidth},${bottomY} ${left + barWidth - r},${bottomY}H${left + r}Q${left},${bottomY} ${left},${bottomY - r}Z`,
                }, flow);
                cursorY = bottomY + 2;
            });
        });

        svg('line', { class: 'd3-zero', x1: pad.left, x2: width - pad.right, y1: zero, y2: zero }, flow);
    }

    /* As saídas de um ciclo na ordem fixa das séries, somando "Outras faturas". */
    function stack(cycle) {
        return data.series.map((series) => ({
            ...series,
            total: cycle.groups.filter((g) => g.id === series.id).reduce((sum, g) => sum + g.total, 0),
        }));
    }

    function drawLegend() {
        const items = [el('li')];
        items[0].append(el('span', 'd3-swatch'), 'Entradas');
        data.series
            .filter((series) => cycles.some((cycle) => cycle.groups.some((g) => g.id === series.id)))
            .forEach((series) => {
                const li = el('li');
                li.append(el('span', `d3-swatch d3-s-${series.slot}`), series.label);
                items.push(li);
            });
        fill(byId('d3-flow-legend'), ...items);
    }

    /* ── Dica flutuante ── */
    function showTip() {
        const cycle = visible()[selected];
        const row = (label, value) => {
            const line = el('div');
            line.append(el('span', '', label), el('span', '', value));
            return line;
        };
        tip.replaceChildren(
            el('strong', '', `${cycle.title}${cycle.current ? ' (atual)' : ''}`),
            row('Saldo em conta', money(cycle.accumulated)),
            row('Resultado', money(cycle.result, true)),
            row('Rendimento', money(cycle.yield, true)),
            row('Entradas', money(cycle.income)),
            row('Saídas', money(cycle.expenses)),
        );
        tip.hidden = false;
        const position = geometry.x(selected + 1);
        const tipWidth = tip.offsetWidth;
        const left = position + 14 + tipWidth > geometry.width ? position - tipWidth - 14 : position + 14;
        tip.style.left = `${Math.max(0, left)}px`;
    }

    /* ── Ciclo selecionado ── */
    function drawCycle() {
        const cycle = visible()[selected];
        const isFirst = selected === 0;
        const opening = isFirst ? anchor.value : cycles[selected - 1].accumulated;

        byId('d3-cycle-title').textContent = `${cycle.title}${cycle.current ? ' (ciclo atual)' : ''}`;
        byId('d3-cycle-sub').textContent = `Ciclo de ${cycle.start} a ${cycle.end}`;
        byId('d3-prev').disabled = selected === 0;
        byId('d3-next').disabled = selected === months - 1;

        const step = (label, value, options = {}, note) => {
            const wrap = el('div');
            const strong = el('strong');
            strong.append(moneyEl(value, options));
            wrap.append(el('span', '', label), strong);
            if (note) wrap.append(el('small', '', note));
            return wrap;
        };

        const closing = step('Saldo em conta no fim', cycle.accumulated, {}, isFirst ? 'Antes do próximo salário' : `Em ${cycle.end}, antes do salário`);
        const bridge = byId('d3-bridge');

        if (isFirst) {
            bridge.style.setProperty('--d3-steps', 5);
            fill(bridge,
                step('Saldo real hoje', anchor.value),
                step('Falta acontecer', cycle.pending, { signed: true, tone: true }),
                step('Rendem nas caixinhas', cycle.yield, { signed: true, tone: true }, 'Até o fim do ciclo'),
                step('Resultado do ciclo inteiro', cycle.result, { signed: true, tone: true }, `Entradas ${money(cycle.income)}, saídas ${money(cycle.expenses)}`),
                closing,
            );
        } else {
            bridge.style.setProperty('--d3-steps', 5);
            fill(bridge,
                step('Começa com', opening),
                step('Entram', cycle.income, { signed: true }),
                step('Saem', -cycle.expenses, { signed: true }, `Faturas ${money(cycle.cards)}`),
                step('Rendem nas caixinhas', cycle.yield, { signed: true, tone: true }),
                closing,
            );
        }

        drawShare(cycle);
        drawEntries(cycle);
        drawGroups(cycle);
    }

    function drawShare(cycle) {
        const label = byId('d3-share-label');
        const bar = byId('d3-share-bar');

        if (cycle.committed === null) {
            label.textContent = 'Ciclo sem receita';
        } else if (cycle.committed > 100) {
            label.textContent = `As saídas passam da receita: ${cycle.committed}% comprometido, faltam ${money(cycle.expenses - cycle.income).replace(MINUS, '')}`;
        } else {
            label.textContent = `${cycle.committed}% da receita comprometida, sobram ${money(cycle.income - cycle.expenses)}`;
        }

        const base = Math.max(cycle.income, cycle.expenses) || 1;
        const parts = stack(cycle).filter((part) => part.total > 0).map((part) => {
            const span = el('span', `d3-s-${part.slot}`);
            span.style.width = `${(part.total / base) * 100}%`;
            span.title = `${part.label}: ${money(part.total)}`;
            return span;
        });

        if (cycle.income > cycle.expenses) parts.push(el('span', 'd3-share-free'));
        if (cycle.expenses > cycle.income && cycle.income > 0) {
            const limit = el('i', 'd3-share-limit');
            limit.style.left = `${(cycle.income / base) * 100}%`;
            parts.push(limit);
        }
        fill(bar, ...parts);
    }

    function listRow(date, description, value, tag, recurring) {
        const row = el('div', 'd3-row');
        if (date) row.append(el('span', 'd3-row-date', date));
        const desc = el('span', 'd3-row-desc', description);
        if (tag) desc.append(el('span', `d3-tag${recurring ? ' is-recurring' : ''}`, tag));
        row.append(desc, moneyEl(value));
        return row;
    }

    function drawEntries(cycle) {
        const rows = cycle.entries.map((e) => listRow(e.date, e.description, e.amount));
        if (!rows.length) rows.push(el('p', 'd3-muted', 'Nenhuma entrada neste ciclo.'));
        const total = listRow(null, 'Total de entradas', cycle.income);
        total.classList.add('is-total');
        fill(byId('d3-entries'), ...rows, total);
    }

    function drawGroups(cycle) {
        const groups = cycle.groups.map((group) => {
            const details = el('details', 'd3-group');
            const summary = el('summary');
            const name = el('span', 'd3-group-name');
            const chev = el('i', 'bi bi-chevron-right d3-chev');
            chev.setAttribute('aria-hidden', 'true');
            const series = seriesById[group.id] || seriesById.others;
            const count = group.rows.length;
            name.append(chev, el('span', `d3-group-key d3-s-${series ? series.slot : 5}`), el('b', '', group.title), el('em', '', `${count} ${count === 1 ? 'item' : 'itens'}`));

            let share = '';
            if (cycle.income > 0) {
                const percent = (group.total / cycle.income) * 100;
                share = percent > 0 && percent < 1 ? '<1%' : `${Math.round(percent)}%`;
            }
            const pct = el('span', 'd3-pct', share);
            pct.title = 'Parte da receita do ciclo';
            summary.append(name, pct, moneyEl(group.total));

            const body = el('div', 'd3-group-body');
            body.append(...group.rows.map((r) => listRow(r.date, r.description, r.amount, r.tag, r.recurring)));
            details.append(summary, body);
            return details;
        });

        if (!groups.length) groups.push(el('p', 'd3-muted', 'Nenhuma saída neste ciclo.'));
        const total = listRow(null, 'Total de saídas', cycle.expenses);
        total.classList.add('is-total');
        fill(byId('d3-groups'), ...groups, total);
    }

    function markTable() {
        document.querySelectorAll('.d3-table tbody tr').forEach((row) => {
            const index = Number(row.dataset.index);
            row.hidden = index >= months;
            row.classList.toggle('is-sel', index === selected);
        });
    }

    /* ── Estado ── */
    function drawCharts() {
        drawTide();
        drawFlow();
    }

    function select(index, { tooltip = false } = {}) {
        const next = Math.max(0, Math.min(months - 1, index));
        if (next === selected && !tooltip) return;
        const changed = next !== selected;
        selected = next;
        cursor.value = next;
        cursor.setAttribute('aria-valuetext', `${visible()[next].title}, saldo ${money(visible()[next].accumulated)}`);
        if (changed) {
            drawCharts();
            drawCycle();
            markTable();
        }
        if (tooltip) showTip();
    }

    function setHorizon(value, { remember = true } = {}) {
        months = Math.max(Number(horizon.min), Math.min(cycles.length, value));
        selected = Math.min(selected, months - 1);
        byId('d3-horizon-value').textContent = months;
        cursor.max = months - 1;
        cursor.value = selected;
        drawCharts();
        drawCycle();
        markTable();

        if (remember) {
            const url = new URL(window.location.href);
            url.searchParams.set('months', months);
            history.replaceState(null, '', url);
        }
    }

    /* ── Interação: clicar ou arrastar escolhe o ciclo; passar o mouse não muda nada ── */
    function indexAt(clientX) {
        const left = plots.getBoundingClientRect().left;
        const px = clientX - left;
        let best = 0;
        let distance = Infinity;
        for (let i = 0; i < months; i++) {
            const gap = Math.abs(geometry.x(i + 1) - px);
            if (gap < distance) { distance = gap; best = i; }
        }
        return best;
    }

    [tide, flow].forEach((chart) => {
        chart.addEventListener('pointerdown', (event) => {
            if (event.pointerType !== 'mouse') chart.setPointerCapture(event.pointerId);
            select(indexAt(event.clientX), { tooltip: true });
        });
        chart.addEventListener('pointermove', (event) => {
            if (event.buttons || event.pointerType === 'touch') {
                select(indexAt(event.clientX), { tooltip: true });
            }
        });
        chart.addEventListener('pointerleave', () => { tip.hidden = true; });
        chart.addEventListener('pointerup', (event) => {
            if (event.pointerType !== 'mouse') tip.hidden = true;
        });
    });

    cursor.addEventListener('input', () => select(Number(cursor.value), { tooltip: true }));
    cursor.addEventListener('blur', () => { tip.hidden = true; });
    // No clique, não no pointerdown: o navegador tira o foco logo depois do pressionar.
    plots.addEventListener('click', () => cursor.focus({ preventScroll: true }));

    horizon.addEventListener('input', () => {
        tip.hidden = true;
        setHorizon(Number(horizon.value));
    });
    byId('d3-prev').addEventListener('click', () => select(selected - 1));
    byId('d3-next').addEventListener('click', () => select(selected + 1));

    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    document.querySelectorAll('.d3-table tbody tr').forEach((row) => {
        row.addEventListener('click', () => {
            select(Number(row.dataset.index));
            byId('d3-cycle').scrollIntoView({ behavior: reduced ? 'auto' : 'smooth', block: 'start' });
        });
    });

    let resizeFrame = null;
    new ResizeObserver(() => {
        cancelAnimationFrame(resizeFrame);
        resizeFrame = requestAnimationFrame(() => { if (geometry) drawCharts(); });
    }).observe(plots);

    // O tema muda por atributo: os gráficos usam variáveis, só o contraste do
    // preenchimento precisa de um novo desenho.
    new MutationObserver(() => drawCharts()).observe(document.documentElement, { attributes: true, attributeFilter: ['data-bs-theme'] });

    drawLegend();
    setHorizon(months, { remember: false });
    cursor.setAttribute('aria-valuetext', `${visible()[0].title}, saldo ${money(visible()[0].accumulated)}`);
})();
</script>
