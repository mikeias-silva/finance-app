@extends('layouts.app')

@section('title', 'Editar Receita Recorrente')

@section('content')
<div class="d-flex align-items-center gap-3 mb-4">
    <a href="{{ route('recurring-incomes.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i>
    </a>
    <div>
        <h1 class="h5 fw-semibold mb-0">Editar receita recorrente</h1>
        <p class="text-muted small mb-0 mt-1">{{ $income->description }}</p>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8 col-xl-6">
        <div class="card">
            <div class="card-body p-4">
                <form action="{{ route('recurring-incomes.update', $income) }}" method="POST">
                    @csrf
                    @method('PUT')

                    @include('recurring-incomes._form')

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-check-lg me-1"></i> Salvar
                        </button>
                        <a href="{{ route('recurring-incomes.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
