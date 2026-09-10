@extends('layouts.app')

@section('title', 'Salários')

@section('content')
<div class="d-flex align-items-center justify-content-between mb-4 gap-3 flex-wrap">
    <div>
        <h1 class="h5 fw-semibold mb-0">Salários</h1>
        <p class="text-muted small mb-0 mt-1">Histórico de salários — permite registrar mudanças futuras sem perder o histórico.</p>
    </div>
    <a href="{{ route('salaries.create') }}" class="btn btn-primary d-flex align-items-center gap-2">
        <i class="bi bi-plus-lg"></i> Novo salário
    </a>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="row g-4 mb-4">
    <div class="col-md-6 col-xl-4">
        <div class="stat-card green">
            <div class="stat-icon"><i class="bi bi-cash-stack"></i></div>
            <span class="stat-label">Salário vigente</span>
            <span class="stat-value">
                {{ $current ? 'R$ '.number_format($current->amount, 2, ',', '.') : '—' }}
            </span>
        </div>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Valor</th>
                    <th>Vigente a partir de</th>
                    <th>Status</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse($salaries as $salary)
                    <tr>
                        <td style="font-family:'DM Mono', monospace;">R$ {{ number_format($salary->amount, 2, ',', '.') }}</td>
                        <td class="text-muted">{{ \Carbon\Carbon::parse($salary->effective_from)->format('d/m/Y') }}</td>
                        <td>
                            @if($current && $salary->id === $current->id)
                                <span class="badge text-bg-success">Vigente</span>
                            @else
                                <span class="badge bg-light text-secondary border">Histórico</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('salaries.edit', $salary) }}" class="btn btn-sm btn-outline-secondary me-1" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form action="{{ route('salaries.destroy', $salary) }}" method="POST" class="d-inline" onsubmit="return confirm('Excluir este salário?')">
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
                            <i class="bi bi-cash-stack fs-2 d-block mb-2 opacity-25"></i>
                            Nenhum salário cadastrado ainda.
                            <a href="{{ route('salaries.create') }}" class="d-block mt-2 small">Adicionar primeiro salário</a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
