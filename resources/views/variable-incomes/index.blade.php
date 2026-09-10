@extends('layouts.app')

@section('title', 'Rendimentos Variáveis')

@section('content')
<div class="d-flex align-items-center justify-content-between mb-4 gap-3 flex-wrap">
    <div>
        <h1 class="h5 fw-semibold mb-0">Rendimentos Variáveis</h1>
        <p class="text-muted small mb-0 mt-1">
            Ciclo salarial:
            <span style="font-family:'DM Mono',monospace;">
                {{ $start->format('d/m/Y') }} → {{ $end->format('d/m/Y') }}
            </span>
        </p>
    </div>
    <a href="{{ route('variable-incomes.create') }}" class="btn btn-primary d-flex align-items-center gap-2">
        <i class="bi bi-plus-lg"></i> Novo rendimento
    </a>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-6 col-xl-4">
        <div class="stat-card green">
            <div class="stat-icon"><i class="bi bi-graph-up-arrow"></i></div>
            <span class="stat-label">Total do mês</span>
            <span class="stat-value">R$ {{ number_format($currentMonthTotal, 2, ',', '.') }}</span>
        </div>
    </div>
    <div class="col-md-6 col-xl-4">
        <div class="stat-card blue">
            <div class="stat-icon"><i class="bi bi-cash-coin"></i></div>
            <span class="stat-label">Total geral</span>
            <span class="stat-value">R$ {{ number_format($totalAmount, 2, ',', '.') }}</span>
        </div>
    </div>
</div>

{{-- Filtro --}}
<div class="card mb-4">
    <div class="card-body py-2 px-3">
        <form method="GET" action="{{ route('variable-incomes.index') }}"
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

            @if($month !== now()->format('Y-m'))
                <a href="{{ route('variable-incomes.index') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-x-lg"></i> Limpar
                </a>
            @endif
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Descrição</th>
                    <th>Data</th>
                    <th>Valor</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse($incomes as $income)
                    <tr>
                        <td class="fw-medium">{{ $income->description }}</td>
                        <td class="text-muted">
                            {{ \Carbon\Carbon::parse($income->received_at)->format('d/m/Y') }}
                            <br><span style="font-size:10px;">{{ $income->created_at->format('H:i') }}</span>
                        </td>
                        <td style="font-family:'DM Mono', monospace; color: var(--green-text);">R$ {{ number_format($income->amount, 2, ',', '.') }}</td>
                        <td class="text-end">
                            <a href="{{ route('variable-incomes.edit', $income) }}" class="btn btn-sm btn-outline-secondary me-1" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form action="{{ route('variable-incomes.destroy', $income) }}" method="POST" class="d-inline" onsubmit="return confirm('Excluir este rendimento?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center py-5 text-muted">
                            <i class="bi bi-graph-up fs-2 d-block mb-2 opacity-25"></i>
                            Nenhum rendimento variável encontrado para este período.
                            <a href="{{ route('variable-incomes.create') }}" class="d-block mt-2 small">Adicionar rendimento</a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($incomes->hasPages())
        <div class="card-footer bg-white border-top py-3 px-4 d-flex justify-content-center">
            {{ $incomes->links() }}
        </div>
    @endif
</div>
@endsection
