@extends('layouts.app')

@section('title', 'Gastos Fixos')

@section('content')
<div class="d-flex align-items-center justify-content-between mb-4 gap-3 flex-wrap">
    <div>
        <h1 class="h5 fw-semibold mb-0">Gastos Fixos</h1>
        <p class="text-muted small mb-0 mt-1">Controle seus compromissos mensais recorrentes.</p>
    </div>
    <a href="{{ route('fixed-expenses.create') }}" class="btn btn-primary d-flex align-items-center gap-2">
        <i class="bi bi-plus-lg"></i> Novo gasto
    </a>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-6 col-xl-4">
        <div class="stat-card green">
            <div class="stat-icon"><i class="bi bi-wallet2"></i></div>
            <span class="stat-label">Total mensal dos ativos</span>
            <span class="stat-value">R$ {{ number_format($activeTotal, 2, ',', '.') }}</span>
        </div>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Descrição</th>
                    <th>Categoria</th>
                    <th>Valor</th>
                    <th>Status</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse($expenses as $expense)
                    <tr>
                        <td class="fw-medium">{{ $expense->description }}</td>
                        <td>
                            @if($expense->category)
                                <span class="badge bg-light text-secondary border">{{ $expense->category }}</span>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                        <td style="font-family:'DM Mono', monospace;">R$ {{ number_format($expense->amount, 2, ',', '.') }}</td>
                        <td>
                            <span class="badge {{ $expense->is_active ? 'text-bg-success' : 'bg-light text-secondary border' }}">
                                {{ $expense->is_active ? 'Ativo' : 'Inativo' }}
                            </span>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('fixed-expenses.edit', $expense) }}" class="btn btn-sm btn-outline-secondary me-1" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form action="{{ route('fixed-expenses.destroy', $expense) }}" method="POST" class="d-inline" onsubmit="return confirm('Excluir este gasto fixo?')">
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
                        <td colspan="5" class="text-center py-5 text-muted">
                            <i class="bi bi-receipt fs-2 d-block mb-2 opacity-25"></i>
                            Nenhum gasto fixo cadastrado ainda.
                            <a href="{{ route('fixed-expenses.create') }}" class="d-block mt-2 small">Adicionar primeiro gasto</a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
