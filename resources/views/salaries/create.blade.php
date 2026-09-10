@extends('layouts.app')

@section('title', 'Novo Salário')

@section('content')
<div class="d-flex align-items-center gap-3 mb-4">
    <a href="{{ route('salaries.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i>
    </a>
    <div>
        <h1 class="h5 fw-semibold mb-0">Novo salário</h1>
        <p class="text-muted small mb-0 mt-1">Registre um novo valor de salário e a partir de quando ele vale.</p>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8 col-xl-6">
        <div class="card">
            <div class="card-body p-4">
                <form action="{{ route('salaries.store') }}" method="POST">
                    @csrf

                    <div class="mb-3">
                        <label for="amount" class="form-label">Valor (R$)</label>
                        <input type="number" id="amount" name="amount" class="form-control @error('amount') is-invalid @enderror"
                               value="{{ old('amount') }}" step="0.01" min="0.01" required autofocus>
                        @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-4">
                        <label for="effective_from" class="form-label">Vigente a partir de</label>
                        <input type="date" id="effective_from" name="effective_from" class="form-control @error('effective_from') is-invalid @enderror"
                               value="{{ old('effective_from') }}" required>
                        <div class="form-text">A partir de quando esse salário é válido. Permite manter histórico se o salário mudar.</div>
                        @error('effective_from')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-check-lg me-1"></i> Salvar
                        </button>
                        <a href="{{ route('salaries.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
