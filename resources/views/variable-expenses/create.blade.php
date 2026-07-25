@extends('layouts.app')

@section('title', 'Nova Movimentação')

@section('content')

<div class="d-flex align-items-center gap-3 mb-4">
    <a href="{{ route('variable-expenses.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i>
    </a>
    <div>
        <h1 class="h5 fw-semibold mb-0">Nova Movimentação</h1>
        <p class="text-muted small mb-0 mt-1">Cadastre um gasto ou débito avulso.</p>
    </div>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-body p-4">
                <form action="{{ route('variable-expenses.store') }}" method="POST">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label" for="description">Descrição</label>
                        <input type="text"
                               id="description"
                               name="description"
                               class="form-control @error('description') is-invalid @enderror"
                               value="{{ old('description') }}"
                               placeholder="Ex: Supermercado, Farmácia..."
                               required autofocus>
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="category">
                            Categoria <span class="text-muted fw-normal">(opcional)</span>
                        </label>
                        <input type="text"
                               id="category"
                               name="category"
                               class="form-control @error('category') is-invalid @enderror"
                               value="{{ old('category') }}"
                               placeholder="Ex: Alimentação, Saúde, Transporte...">
                        @error('category')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-sm-6">
                            <label class="form-label" for="amount">Valor (R$)</label>
                            <input type="number"
                                   id="amount"
                                   name="amount"
                                   class="form-control @error('amount') is-invalid @enderror"
                                   value="{{ old('amount') }}"
                                   placeholder="0,00"
                                   step="0.01" min="0.01"
                                   required>
                            @error('amount')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="paid_at">Data do pagamento</label>
                            <input type="date"
                                   id="paid_at"
                                   name="paid_at"
                                   class="form-control @error('paid_at') is-invalid @enderror"
                                   value="{{ old('paid_at', now()->format('Y-m-d')) }}"
                                   required>
                            @error('paid_at')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-check-lg me-1"></i> Salvar
                        </button>
                        <a href="{{ route('variable-expenses.index') }}" class="btn btn-outline-secondary">
                            Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection
