@extends('layouts.app')

@section('title', 'Novo Rendimento')

@section('content')
<div class="d-flex align-items-center gap-3 mb-4">
    <a href="{{ route('variable-incomes.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i>
    </a>
    <div>
        <h1 class="h5 fw-semibold mb-0">Novo rendimento variável</h1>
        <p class="text-muted small mb-0 mt-1">Registre uma nova entrada financeira.</p>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8 col-xl-6">
        <div class="card">
            <div class="card-body p-4">
                <form action="{{ route('variable-incomes.store') }}" method="POST">
                    @csrf

                    <div class="mb-3">
                        <label for="description" class="form-label">Descrição</label>
                        <input type="text" id="description" name="description" class="form-control @error('description') is-invalid @enderror" value="{{ old('description') }}" required autofocus>
                        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label for="amount" class="form-label">Valor (R$)</label>
                        <input type="number" id="amount" name="amount" class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount') }}" step="0.01" min="0.01" required>
                        @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-4">
                        <label for="received_at" class="form-label">Data do recebimento</label>
                        <input type="date" id="received_at" name="received_at" class="form-control @error('received_at') is-invalid @enderror" value="{{ old('received_at', now()->format('Y-m-d')) }}" required>
                        @error('received_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-check-lg me-1"></i> Salvar
                        </button>
                        <a href="{{ route('variable-incomes.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
