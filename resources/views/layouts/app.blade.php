<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Finance') — Finance App</title>

    {{-- Aplica o tema antes de renderizar, senão a página pisca clara ao carregar. --}}
    <script>
        (() => {
            const saved = localStorage.getItem('theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.setAttribute('data-bs-theme', saved || (prefersDark ? 'dark' : 'light'));
        })();
    </script>

    {{-- Bootstrap 5 --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    {{-- Bootstrap Icons --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    {{-- Google Fonts --}}
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">

    <style>
        :root, [data-bs-theme="light"] {
            --sidebar-width: 240px;
            --topbar-height: 60px;

            --sidebar-bg: #0f172a;
            --sidebar-text: #94a3b8;
            --sidebar-active-bg: #1e293b;
            --sidebar-active-text: #f8fafc;
            --sidebar-icon: #475569;
            --sidebar-border: #1e293b;
            --sidebar-hover-bg: #1e293b;
            --sidebar-hover-text: #e2e8f0;
            --sidebar-label: #94a3b8;

            --body-bg: #f8fafc;
            --surface: #ffffff;
            --surface-alt: #f1f5f9;
            --surface-hover: #f8fafc;
            --card-border: #e2e8f0;
            --text-primary: #0f172a;
            --text-muted: #64748b;
            --input-border: #e2e8f0;
            --input-focus: #94a3b8;
            --btn-primary-bg: #0f172a;
            --btn-primary-hover: #1e293b;
            --shadow: 0 1px 3px rgba(0,0,0,.04);

            /* Preenchimentos (barras, ícones, pontos) — vivos. */
            --green: #10b981;
            --red: #ef4444;
            --yellow: #f59e0b;
            --blue: #3b82f6;
            /* Texto — tons mais escuros, porque os vivos ficam em 2,1–3,8:1 sobre
               fundo claro e não alcançam o mínimo AA de 4,5:1. */
            --green-text: #047857;
            --red-text: #dc2626;
            --yellow-text: #b45309;
            --blue-text: #2563eb;
            --green-soft: #d1fae5;
            --red-soft: #fee2e2;
            --yellow-soft: #fef3c7;
            --blue-soft: #dbeafe;
            --alert-success-text: #065f46;
            --alert-danger-text: #991b1b;
        }

        /* Tema escuro: superfícies dessaturadas em azul e cores de destaque
           mais claras, para manter contraste AA sobre fundo escuro. */
        [data-bs-theme="dark"] {
            --sidebar-bg: #080d17;
            --sidebar-text: #8b9ab1;
            --sidebar-active-bg: #16203a;
            --sidebar-active-text: #f1f5f9;
            --sidebar-icon: #5b6b85;
            --sidebar-border: #16203a;
            --sidebar-hover-bg: #131c31;
            --sidebar-hover-text: #cbd5e1;
            --sidebar-label: #8b9ab1;

            --body-bg: #0b1220;
            --surface: #121b2e;
            --surface-alt: #1a2438;
            --surface-hover: #1a2438;
            --card-border: #263353;
            --text-primary: #e8eef9;
            --text-muted: #a3b1c6;
            --input-border: #263353;
            --input-focus: #64748b;
            --btn-primary-bg: #2563eb;
            --btn-primary-hover: #1d4ed8;
            --shadow: 0 1px 3px rgba(0,0,0,.4);

            --green: #34d399;
            --red: #f87171;
            --yellow: #fbbf24;
            --blue: #60a5fa;
            /* No escuro os tons vivos já passam de 6:1, então texto usa os mesmos. */
            --green-text: #34d399;
            --red-text: #f87171;
            --yellow-text: #fbbf24;
            --blue-text: #60a5fa;
            --green-soft: rgba(52,211,153,.16);
            --red-soft: rgba(248,113,113,.16);
            --yellow-soft: rgba(251,191,36,.16);
            --blue-soft: rgba(96,165,250,.16);
            --alert-success-text: #6ee7b7;
            --alert-danger-text: #fca5a5;
        }

        /* bg-light/bg-white/text-dark do Bootstrap são literais, não seguem o tema.
           No escuro precisam apontar para as superfícies do tema — menos em cima
           de bg-warning, onde o texto escuro é o que dá contraste no amarelo. */
        [data-bs-theme="dark"] .bg-light { background-color: var(--surface-alt) !important; }
        [data-bs-theme="dark"] .bg-white { background-color: var(--surface) !important; }
        [data-bs-theme="dark"] .text-dark:not(.bg-warning) { color: var(--text-primary) !important; }
        [data-bs-theme="dark"] .border { border-color: var(--card-border) !important; }
        /* O cinza padrão do btn-outline-secondary (#6c757d) fica em 4,48:1 no claro
           e 3,7:1 no escuro — abaixo do mínimo AA nos dois. */
        .btn-outline-secondary { color: var(--text-muted); border-color: var(--card-border); }
        .btn-outline-secondary:hover { color: var(--text-primary); background: var(--surface-alt); border-color: var(--input-focus); }
        [data-bs-theme="dark"] .btn-outline-secondary {
            color: var(--text-muted);
            border-color: var(--card-border);
        }
        [data-bs-theme="dark"] .btn-outline-secondary:hover {
            color: var(--text-primary);
            background: var(--surface-alt);
            border-color: var(--input-focus);
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--body-bg);
            color: var(--text-primary);
            margin: 0;
        }

        /* ── Sidebar ── */
        #sidebar {
            position: fixed;
            top: 0; left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: var(--sidebar-bg);
            display: flex;
            flex-direction: column;
            z-index: 1000;
            transition: transform .25s ease;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 20px 20px 16px;
            border-bottom: 1px solid var(--sidebar-border);
            text-decoration: none;
        }
        .sidebar-brand .brand-icon {
            width: 34px; height: 34px;
            background: var(--green);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 16px; color: #fff;
            flex-shrink: 0;
        }
        .sidebar-brand .brand-name {
            font-size: 15px;
            font-weight: 600;
            color: #f8fafc;
            letter-spacing: -.3px;
        }

        .sidebar-section {
            padding: 20px 12px 6px;
        }
        .sidebar-label {
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: var(--sidebar-label);
            padding: 0 8px;
            margin-bottom: 4px;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 12px;
            border-radius: 8px;
            color: var(--sidebar-text);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: background .15s, color .15s;
            margin-bottom: 2px;
        }
        .sidebar-link i {
            font-size: 16px;
            color: var(--sidebar-icon);
            width: 18px;
            text-align: center;
            transition: color .15s;
        }
        .sidebar-link:hover {
            background: var(--sidebar-hover-bg);
            color: var(--sidebar-hover-text);
        }
        .sidebar-link:hover i { color: var(--sidebar-text); }
        .sidebar-link.active {
            background: var(--sidebar-active-bg);
            color: var(--sidebar-active-text);
        }
        .sidebar-link.active i { color: var(--green); }

        .sidebar-footer {
            margin-top: auto;
            padding: 16px 12px;
            border-top: 1px solid var(--sidebar-border);
        }

        /* ── Main ── */
        #main {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ── Topbar ── */
        #topbar {
            height: var(--topbar-height);
            background: var(--surface);
            border-bottom: 1px solid var(--card-border);
            display: flex;
            align-items: center;
            padding: 0 28px;
            gap: 12px;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .topbar-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-primary);
            flex: 1;
        }
        .topbar-date {
            font-size: 13px;
            color: var(--text-muted);
            font-family: 'DM Mono', monospace;
        }
        .theme-toggle {
            width: 34px; height: 34px;
            display: flex; align-items: center; justify-content: center;
            border: 1px solid var(--card-border);
            border-radius: 8px;
            background: var(--surface-alt);
            color: var(--text-muted);
            font-size: 15px;
            cursor: pointer;
            transition: background .15s, color .15s, border-color .15s;
            flex-shrink: 0;
        }
        .theme-toggle:hover {
            background: var(--surface-hover);
            color: var(--text-primary);
            border-color: var(--input-focus);
        }
        .theme-toggle:focus-visible {
            outline: 2px solid var(--blue);
            outline-offset: 2px;
        }

        /* ── Content ── */
        #content {
            padding: 28px;
            flex: 1;
        }

        /* ── Cards ── */
        .card {
            border: 1px solid var(--card-border);
            border-radius: 12px;
            box-shadow: var(--shadow);
        }
        .card-header {
            background: var(--surface);
            border-bottom: 1px solid var(--card-border);
            border-radius: 12px 12px 0 0 !important;
            padding: 16px 20px;
            font-weight: 600;
            font-size: 14px;
        }

        /* ── Stat cards ── */
        .stat-card {
            background: var(--surface);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .stat-card .stat-label {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .6px;
            color: var(--text-muted);
        }
        .stat-card .stat-value {
            font-size: 26px;
            /*font-weight: 600;*/
            letter-spacing: -1px;
            font-family: 'DM Mono', monospace;
            line-height: 1;
        }
        .stat-card .stat-icon {
            width: 38px; height: 38px;
            border-radius: 9px;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px;
            align-self: flex-start;
        }
        .stat-card.green .stat-value { color: var(--green-text); }
        .stat-card.green .stat-icon  { background: var(--green-soft); color: var(--green-text); }
        .stat-card.red   .stat-value { color: var(--red-text); }
        .stat-card.red   .stat-icon  { background: var(--red-soft); color: var(--red-text); }
        .stat-card.blue  .stat-value { color: var(--blue-text); }
        .stat-card.blue  .stat-icon  { background: var(--blue-soft); color: var(--blue-text); }
        .stat-card.yellow .stat-value { color: var(--yellow-text); }
        .stat-card.yellow .stat-icon  { background: var(--yellow-soft); color: var(--yellow-text); }

        /* ── Badge de cartão ── */
        .card-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 9px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            background: var(--surface-alt);
            color: var(--text-primary);
        }
        .card-badge .dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        /* ── Tabela ── */
        .table th {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .6px;
            color: var(--text-muted);
            border-bottom-width: 1px;
            padding: 10px 16px;
        }
        .table td {
            padding: 12px 16px;
            vertical-align: middle;
            font-size: 14px;
            border-color: var(--surface-alt);
        }
        .table tbody tr:hover { background: var(--surface-hover); }

        /* ── Botões ── */
        .btn { font-size: 13px; font-weight: 500; border-radius: 8px; }
        .btn-primary { background: var(--btn-primary-bg); border-color: var(--btn-primary-bg); }
        .btn-primary:hover { background: var(--btn-primary-hover); border-color: var(--btn-primary-hover); }
        .btn-sm { padding: 5px 10px; font-size: 12px; }

        /* ── Formulários ── */
        .form-label { font-size: 13px; font-weight: 500; color: var(--text-primary); margin-bottom: 5px; }
        .form-control, .form-select {
            font-size: 14px;
            border-color: var(--input-border);
            border-radius: 8px;
            padding: 9px 12px;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--input-focus);
            box-shadow: 0 0 0 3px rgba(148,163,184,.15);
        }

        /* ── Alertas flash ── */
        .alert { border-radius: 10px; font-size: 14px; border: none; }
        .alert-success { background: var(--green-soft); color: var(--alert-success-text); }
        .alert-danger  { background: var(--red-soft); color: var(--alert-danger-text); }

        /* ── Projeção mensal ── */
        .month-projection {
            background: var(--surface);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            overflow: hidden;
        }
        .month-projection .month-header {
            padding: 14px 20px;
            border-bottom: 1px solid var(--surface-alt);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .month-projection .month-name {
            font-size: 14px;
            font-weight: 600;
            text-transform: capitalize;
        }
        .month-projection .month-balance {
            font-family: 'DM Mono', monospace;
            font-size: 14px;
            font-weight: 600;
        }
        .month-projection .month-balance.positive { color: var(--green-text); }
        .month-projection .month-balance.negative { color: var(--red-text); }
        .month-bar {
            height: 4px;
            background: var(--surface-alt);
        }
        .month-bar .bar-fill {
            height: 100%;
            transition: width .4s ease;
        }
        .month-bar .bar-fill.positive { background: var(--green); }
        .month-bar .bar-fill.negative { background: var(--red); }

        /* ── Mobile ── */
        @media (max-width: 768px) {
            #sidebar { transform: translateX(-100%); }
            #sidebar.show { transform: translateX(0); }
            #main { margin-left: 0; }
            #content { padding: 16px; }
        }
    </style>

    @stack('styles')
</head>
<body>

{{-- ── Sidebar ── --}}
<nav id="sidebar">
    <a href="{{ route('dashboard') }}" class="sidebar-brand">
        <div class="brand-icon"><i class="bi bi-bar-chart-fill"></i></div>
        <span class="brand-name">Finance</span>
    </a>

    <div class="sidebar-section flex-grow-1 overflow-auto">
        <div class="sidebar-label">Visão geral</div>
        <a href="{{ route('dashboard') }}"
           class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <i class="bi bi-grid-1x2"></i> Dashboard
        </a>
        <a href="{{ route('investments.index') }}"
           class="sidebar-link {{ request()->routeIs('investments.*') ? 'active' : '' }}">
            <i class="bi bi-piggy-bank"></i> Investimentos
        </a>

        <div class="sidebar-label mt-3">Lançamentos</div>
        <a href="{{ route('purchases.index') }}"
           class="sidebar-link {{ request()->routeIs('purchases.*') ? 'active' : '' }}">
            <i class="bi bi-credit-card"></i> Compras parceladas
        </a>
        <a href="{{ route('fixed-expenses.index') }}"
           class="sidebar-link {{ request()->routeIs('fixed-expenses.*') ? 'active' : '' }}">
            <i class="bi bi-receipt"></i> Gastos fixos
        </a>
        <a href="{{ route('recurring-incomes.index') }}"
           class="sidebar-link {{ request()->routeIs('recurring-incomes.*') ? 'active' : '' }}">
            <i class="bi bi-cash-coin"></i> Receitas recorrentes
        </a>
        <a href="{{ route('variable-incomes.index') }}"
           class="sidebar-link {{ request()->routeIs('variable-incomes.*') ? 'active' : '' }}">
            <i class="bi bi-graph-up-arrow"></i> Rendimentos variáveis
        </a>
        <a href="{{ route('variable-expenses.index') }}"
           class="sidebar-link {{ request()->routeIs('variable-expenses.*') ? 'active' : '' }}">
            <i class="bi bi-arrow-left-right"></i> Movimentações de conta
        </a>

        <div class="sidebar-label mt-3">Configurações</div>
        <a href="{{ route('credit-cards.index') }}"
           class="sidebar-link {{ request()->routeIs('credit-cards.*') ? 'active' : '' }}">
            <i class="bi bi-wallet2"></i> Cartões
        </a>
        <a href="{{ route('salaries.index') }}"
           class="sidebar-link {{ request()->routeIs('salaries.*') ? 'active' : '' }}">
            <i class="bi bi-cash-stack"></i> Salário
        </a>
    </div>

    <div class="sidebar-footer">
        <div style="font-size:11px; color:var(--sidebar-label); font-weight:500;">Finance App v1.0</div>
    </div>
</nav>

{{-- ── Main ── --}}
<div id="main">

    {{-- Topbar --}}
    <header id="topbar">
        <button class="btn btn-sm d-md-none me-1"
                onclick="document.getElementById('sidebar').classList.toggle('show')"
                style="border:none;background:none;font-size:20px;padding:0;">
            <i class="bi bi-list"></i>
        </button>
        <span class="topbar-title">@yield('title', 'Dashboard')</span>
        <span class="topbar-date">{{ now()->translatedFormat('d \d\e F, Y') }}</span>
        <button type="button" id="themeToggle" class="theme-toggle" title="Alternar tema" aria-label="Alternar tema claro/escuro">
            <i class="bi" data-role="icon"></i>
        </button>
        <form method="POST" action="{{ route('logout') }}" style="display:flex;">
            @csrf
            <button type="submit" class="theme-toggle" title="Sair" aria-label="Sair do sistema">
                <i class="bi bi-box-arrow-right"></i>
            </button>
        </form>
    </header>

    {{-- Conteúdo --}}
    <main id="content">

        {{-- Flash messages --}}
        @if(session('success'))
            <div class="alert alert-success d-flex align-items-center gap-2 mb-4">
                <i class="bi bi-check-circle-fill"></i>
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger d-flex align-items-center gap-2 mb-4">
                <i class="bi bi-exclamation-triangle-fill"></i>
                {{ session('error') }}
            </div>
        @endif

        @yield('content')
    </main>
</div>

{{-- Bootstrap JS --}}
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    (() => {
        const root = document.documentElement;
        const button = document.getElementById('themeToggle');
        const icon = button.querySelector('[data-role="icon"]');

        // Ícone mostra o tema para o qual se vai, não o atual.
        function render() {
            const isDark = root.getAttribute('data-bs-theme') === 'dark';
            icon.className = isDark ? 'bi bi-sun' : 'bi bi-moon-stars';
            button.title = isDark ? 'Mudar para tema claro' : 'Mudar para tema escuro';
        }

        button.addEventListener('click', () => {
            const next = root.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
            root.setAttribute('data-bs-theme', next);
            localStorage.setItem('theme', next);
            render();
        });

        render();
    })();
</script>
@stack('scripts')
</body>
</html>
