@extends('layouts.app')

@section('title', 'Movimentações de Conta')

@section('content')

<div class="d-flex align-items-center justify-content-between mb-4 gap-3 flex-wrap">
    <div>
        <h1 class="h5 fw-semibold mb-0">Movimentações de Conta</h1>
        <p class="text-muted small mb-0 mt-1">
            Ciclo salarial:
            <span style="font-family:'DM Mono',monospace;">
                {{ $start->format('d/m/Y') }} → {{ $end->format('d/m/Y') }}
            </span>
        </p>
    </div>
    <a href="{{ route('variable-expenses.create') }}" class="btn btn-primary d-flex align-items-center gap-2">
        <i class="bi bi-plus-lg"></i> Nova movimentação
    </a>
</div>

{{-- Stat card --}}
<div class="row g-4 mb-4">
    <div class="col-md-6 col-xl-4">
        <div class="stat-card red">
            <div class="stat-icon"><i class="bi bi-arrow-down-circle"></i></div>
            <span class="stat-label">Total do mês</span>
            <span class="stat-value">R$ {{ number_format($monthTotal, 2, ',', '.') }}</span>
        </div>
    </div>
</div>

{{-- Filtros --}}
<div class="card mb-4">
    <div class="card-body py-2 px-3">
        <form method="GET" action="{{ route('variable-expenses.index') }}"
              class="d-flex align-items-center gap-2 flex-wrap">

            <div class="d-flex align-items-center gap-1">
                <label class="text-muted small fw-medium mb-0 me-1">Mês:</label>
                <input type="month"
                       name="month"
                       value="{{ $month }}"
                       class="form-control form-control-sm"
                       style="width:150px;"
                       onchange="this.form.submit()">
            </div>

            @if($categories->isNotEmpty())
            <div class="d-flex align-items-center gap-1">
                <label class="text-muted small fw-medium mb-0 me-1">Categoria:</label>
                <select name="category"
                        class="form-select form-select-sm"
                        style="width:180px;"
                        onchange="this.form.submit()">
                    <option value="">Todas</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat }}" {{ request('category') === $cat ? 'selected' : '' }}>
                            {{ $cat }}
                        </option>
                    @endforeach
                </select>
            </div>
            @endif

            @if(request('category') || request('month') !== now()->format('Y-m'))
                <a href="{{ route('variable-expenses.index') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-x-lg"></i> Limpar
                </a>
            @endif
        </form>
    </div>
</div>

{{-- Tabela --}}
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Descrição</th>
                    <th>Categoria</th>
                    <th>Valor</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse($expenses as $expense)
                <tr>
                    <td class="text-muted small" style="white-space:nowrap;">
                        {{ \Carbon\Carbon::parse($expense->paid_at)->format('d/m/Y') }}
                    </td>
                    <td class="fw-medium">{{ $expense->description }}</td>
                    <td>
                        @if($expense->category)
                            <span class="badge bg-light text-secondary border" style="font-size:11px;">
                                {{ $expense->category }}
                            </span>
                        @else
                            <span class="text-muted small">—</span>
                        @endif
                    </td>
                    <td style="font-family:'DM Mono',monospace; color:var(--red); white-space:nowrap;">
                        R$ {{ number_format($expense->amount, 2, ',', '.') }}
                    </td>
                    <td class="text-end">
                        <a href="{{ route('variable-expenses.edit', $expense) }}"
                           class="btn btn-sm btn-outline-secondary me-1" title="Editar">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form action="{{ route('variable-expenses.destroy', $expense) }}"
                              method="POST" class="d-inline"
                              onsubmit="return confirm('Excluir esta movimentação?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center py-5 text-muted">
                        <i class="bi bi-inbox fs-2 d-block mb-2 opacity-25"></i>
                        Nenhuma movimentação encontrada para este período.
                        <a href="{{ route('variable-expenses.create') }}" class="d-block mt-2 small">
                            Adicionar movimentação
                        </a>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($expenses->hasPages())
        <div class="card-footer bg-white border-top py-3 px-4 d-flex justify-content-center">
        {{ $expenses->links() }}
    </div>
    @endif
</div>

@endsection
