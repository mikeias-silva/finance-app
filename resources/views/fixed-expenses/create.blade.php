@extends('layouts.app')

@section('title', 'Novo Gasto Fixo')

@section('content')
<div class="d-flex align-items-center gap-3 mb-4">
    <a href="{{ route('fixed-expenses.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i>
    </a>
    <div>
        <h1 class="h5 fw-semibold mb-0">Novo gasto fixo</h1>
        <p class="text-muted small mb-0 mt-1">Cadastre uma despesa recorrente mensal.</p>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8 col-xl-6">
        <div class="card">
            <div class="card-body p-4">
                <form action="{{ route('fixed-expenses.store') }}" method="POST">
                    @csrf

                    <div class="mb-3">
                        <label for="description" class="form-label">Descrição</label>
                        <input type="text" id="description" name="description" class="form-control @error('description') is-invalid @enderror" value="{{ old('description') }}" required autofocus>
                        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label for="category" class="form-label">Categoria <span class="text-muted fw-normal">(opcional)</span></label>
                        <input type="text" id="category" name="category" class="form-control @error('category') is-invalid @enderror" value="{{ old('category') }}">
                        @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-4">
                        <label for="amount" class="form-label">Valor mensal (R$)</label>
                        <input type="number" id="amount" name="amount" class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount') }}" step="0.01" min="0.01" required>
                        @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="form-check form-switch mb-4">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" @checked(old('is_active', '1') == '1')>
                        <label class="form-check-label" for="is_active">Gasto ativo</label>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-check-lg me-1"></i> Salvar
                        </button>
                        <a href="{{ route('fixed-expenses.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
