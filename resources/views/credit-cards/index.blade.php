@extends('layouts.app')

@section('title', 'Cartões')

@section('content')
<div class="d-flex align-items-center justify-content-between mb-4 gap-3 flex-wrap">
    <div>
        <h1 class="h5 fw-semibold mb-0">Cartões de Crédito</h1>
        <p class="text-muted small mb-0 mt-1">Gerencie os cartões usados nas compras parceladas.</p>
    </div>
    <a href="{{ route('credit-cards.create') }}" class="btn btn-primary d-flex align-items-center gap-2">
        <i class="bi bi-plus-lg"></i> Novo cartão
    </a>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Cartão</th>
                    <th>Fechamento</th>
                    <th>Vencimento</th>
                    <th>Compras</th>
                    <th>Status</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse($cards as $card)
                    <tr>
                        <td class="fw-medium">
                            <span class="d-inline-block rounded-circle me-2" style="width:10px;height:10px;background:{{ $card->color }};"></span>
                            {{ $card->name }}
                        </td>
                        <td class="text-muted small">Dia {{ $card->closing_day }}</td>
                        <td class="text-muted small">{{ $card->due_day ? 'Dia '.$card->due_day : '—' }}</td>
                        <td class="text-muted small">{{ $card->purchases_count }}</td>
                        <td>
                            <span class="badge {{ $card->is_active ? 'text-bg-success' : 'bg-light text-secondary border' }}">
                                {{ $card->is_active ? 'Ativo' : 'Inativo' }}
                            </span>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('credit-cards.edit', $card) }}" class="btn btn-sm btn-outline-secondary me-1" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form action="{{ route('credit-cards.destroy', $card) }}" method="POST" class="d-inline" onsubmit="return confirm('Excluir este cartão?')">
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
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="bi bi-wallet2 fs-2 d-block mb-2 opacity-25"></i>
                            Nenhum cartão cadastrado ainda.
                            <a href="{{ route('credit-cards.create') }}" class="d-block mt-2 small">Adicionar primeiro cartão</a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
