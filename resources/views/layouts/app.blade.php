<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Finance') — Finance App</title>

    {{-- Bootstrap 5 --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    {{-- Bootstrap Icons --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    {{-- Google Fonts --}}
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">

    <style>
        :root {
            --sidebar-width: 240px;
            --sidebar-bg: #0f172a;
            --sidebar-text: #94a3b8;
            --sidebar-active-bg: #1e293b;
            --sidebar-active-text: #f8fafc;
            --sidebar-icon: #475569;
            --topbar-height: 60px;
            --body-bg: #f8fafc;
            --card-border: #e2e8f0;
            --text-primary: #0f172a;
            --text-muted: #64748b;
            --green: #10b981;
            --red: #ef4444;
            --yellow: #f59e0b;
            --blue: #3b82f6;
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
            border-bottom: 1px solid #1e293b;
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
            color: #334155;
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
            background: #1e293b;
            color: #e2e8f0;
        }
        .sidebar-link:hover i { color: #94a3b8; }
        .sidebar-link.active {
            background: var(--sidebar-active-bg);
            color: var(--sidebar-active-text);
        }
        .sidebar-link.active i { color: var(--green); }

        .sidebar-footer {
            margin-top: auto;
            padding: 16px 12px;
            border-top: 1px solid #1e293b;
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
            background: #fff;
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

        /* ── Content ── */
        #content {
            padding: 28px;
            flex: 1;
        }

        /* ── Cards ── */
        .card {
            border: 1px solid var(--card-border);
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,.04);
        }
        .card-header {
            background: #fff;
            border-bottom: 1px solid var(--card-border);
            border-radius: 12px 12px 0 0 !important;
            padding: 16px 20px;
            font-weight: 600;
            font-size: 14px;
        }

        /* ── Stat cards ── */
        .stat-card {
            background: #fff;
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
            font-weight: 600;
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
        .stat-card.green .stat-value { color: var(--green); }
        .stat-card.green .stat-icon  { background: #d1fae5; color: var(--green); }
        .stat-card.red   .stat-value { color: var(--red); }
        .stat-card.red   .stat-icon  { background: #fee2e2; color: var(--red); }
        .stat-card.blue  .stat-value { color: var(--blue); }
        .stat-card.blue  .stat-icon  { background: #dbeafe; color: var(--blue); }
        .stat-card.yellow .stat-value { color: var(--yellow); }
        .stat-card.yellow .stat-icon  { background: #fef3c7; color: var(--yellow); }

        /* ── Badge de cartão ── */
        .card-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 9px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            background: #f1f5f9;
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
            border-color: #f1f5f9;
        }
        .table tbody tr:hover { background: #f8fafc; }

        /* ── Botões ── */
        .btn { font-size: 13px; font-weight: 500; border-radius: 8px; }
        .btn-primary { background: #0f172a; border-color: #0f172a; }
        .btn-primary:hover { background: #1e293b; border-color: #1e293b; }
        .btn-sm { padding: 5px 10px; font-size: 12px; }

        /* ── Formulários ── */
        .form-label { font-size: 13px; font-weight: 500; color: var(--text-primary); margin-bottom: 5px; }
        .form-control, .form-select {
            font-size: 14px;
            border-color: #e2e8f0;
            border-radius: 8px;
            padding: 9px 12px;
        }
        .form-control:focus, .form-select:focus {
            border-color: #94a3b8;
            box-shadow: 0 0 0 3px rgba(148,163,184,.15);
        }

        /* ── Alertas flash ── */
        .alert { border-radius: 10px; font-size: 14px; border: none; }
        .alert-success { background: #d1fae5; color: #065f46; }
        .alert-danger  { background: #fee2e2; color: #991b1b; }

        /* ── Projeção mensal ── */
        .month-projection {
            background: #fff;
            border: 1px solid var(--card-border);
            border-radius: 12px;
            overflow: hidden;
        }
        .month-projection .month-header {
            padding: 14px 20px;
            border-bottom: 1px solid #f1f5f9;
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
        .month-projection .month-balance.positive { color: var(--green); }
        .month-projection .month-balance.negative { color: var(--red); }
        .month-bar {
            height: 4px;
            background: #f1f5f9;
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
        <a href="#"
           class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <i class="bi bi-grid-1x2"></i> Dashboard
        </a>

        <div class="sidebar-label mt-3">Lançamentos</div>
        <a href=""
           class="sidebar-link {{ request()->routeIs('purchases.*') ? 'active' : '' }}">
            <i class="bi bi-credit-card"></i> Compras parceladas
        </a>
        <a href="{{ route('fixed-expenses.index') }}"
           class="sidebar-link {{ request()->routeIs('fixed-expenses.*') ? 'active' : '' }}">
            <i class="bi bi-receipt"></i> Gastos fixos
        </a>
        <a href="{{ route('variable-incomes.index') }}"
           class="sidebar-link {{ request()->routeIs('variable-incomes.*') ? 'active' : '' }}">
            <i class="bi bi-graph-up-arrow"></i> Rendimentos variáveis
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
        <div style="font-size:11px; color:#334155; font-weight:500;">Finance App v1.0</div>
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
@stack('scripts')
</body>
</html>
