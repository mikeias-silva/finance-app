@extends('layouts.app')

@section('title', 'Receitas Recorrentes')

@section('content')
<div class="d-flex align-items-center justify-content-between mb-4 gap-3 flex-wrap">
    <div>
        <h1 class="h5 fw-semibold mb-0">Receitas Recorrentes</h1>
        <p class="text-muted small mb-0 mt-1">Valores que caem junto do salário todo ciclo, sem ser o salário.</p>
    </div>
    <a href="{{ route('recurring-incomes.create') }}" class="btn btn-primary d-flex align-items-center gap-2">
        <i class="bi bi-plus-lg"></i> Nova receita
    </a>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-6 col-xl-4">
        <div class="stat-card green">
            <div class="stat-icon"><i class="bi bi-cash-coin"></i></div>
            <span class="stat-label">Total mensal das ativas</span>
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
                    <th>Valor</th>
                    <th>Vigência</th>
                    <th>Status</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse($incomes as $income)
                    <tr>
                        <td class="fw-medium">{{ $income->description }}</td>
                        <td style="font-family:'DM Mono', monospace;">R$ {{ number_format($income->amount, 2, ',', '.') }}</td>
                        <td class="small text-muted">
                            a partir de {{ $income->effective_from->format('d/m/Y') }}
                            @if($income->valid_until)
                                <br>até {{ $income->valid_until->format('d/m/Y') }}
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $income->is_active ? 'text-bg-success' : 'bg-light text-secondary border' }}">
                                {{ $income->is_active ? 'Ativa' : 'Inativa' }}
                            </span>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('recurring-incomes.edit', $income) }}" class="btn btn-sm btn-outline-secondary me-1" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form action="{{ route('recurring-incomes.destroy', $income) }}" method="POST" class="d-inline" onsubmit="return confirm('Excluir esta receita recorrente?')">
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
                            <i class="bi bi-cash-coin fs-2 d-block mb-2 opacity-25"></i>
                            Nenhuma receita recorrente cadastrada ainda.
                            <a href="{{ route('recurring-incomes.create') }}" class="d-block mt-2 small">Adicionar primeira receita</a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
