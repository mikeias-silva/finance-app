@extends('layouts.app')

@section('title', 'Compras parceladas')

@section('content')

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h5 fw-semibold mb-0">Compras parceladas</h1>
        <p class="text-muted small mb-0 mt-1">Todas as compras e suas parcelas futuras</p>
    </div>
    <a href="{{ route('purchases.create') }}" class="btn btn-primary d-flex align-items-center gap-2">
        <i class="bi bi-plus-lg"></i> Nova compra
    </a>
</div>

{{-- Filtro por cartão --}}
<div class="card mb-4">
    <div class="card-body py-2 px-3">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="text-muted small fw-medium me-1">Filtrar:</span>
            <a href="{{ route('purchases.index') }}"
               class="btn btn-sm {{ !request('card') ? 'btn-primary' : 'btn-outline-secondary' }}">
                Todos
            </a>
            @foreach($cards as $card)
            <a href="{{ route('purchases.index', ['card' => $card->id]) }}"
               class="btn btn-sm {{ request('card') == $card->id ? 'btn-primary' : 'btn-outline-secondary' }}"
               style="{{ request('card') == $card->id ? '' : "border-color:{$card->color};color:{$card->color};" }}">
                <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:{{ $card->color }};margin-right:4px;"></span>
                {{ $card->name }}
            </a>
            @endforeach
        </div>
    </div>
</div>

{{-- Tabela --}}
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Descrição</th>
                    <th>Cartão</th>
                    <th>Data da compra</th>
                    <th>Valor total</th>
                    <th>Parcelas</th>
                    <th>Valor/parcela</th>
                    <th>Última parcela</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($purchases as $purchase)
                @php
                    $installmentValue = $purchase->total_amount / $purchase->installments;
                    $lastInstallment  = \Carbon\Carbon::parse($purchase->purchased_at)
                                            ->addMonths($purchase->installments - 1);
                    $paidCount        = max(0, now()->startOfMonth()->diffInMonths(
                                            \Carbon\Carbon::parse($purchase->purchased_at)->startOfMonth()
                                        ));
                    $remaining        = max(0, $purchase->installments - $paidCount);
                @endphp
                <tr>
                    <td>
                        <div class="fw-medium">
                            {{ $purchase->description }}
                            @if($purchase->excluded_from_projection)
                                <span class="badge bg-light text-dark border ms-1" style="font-size:11px;">Fora dos cálculos</span>
                            @endif
                        </div>
                        @if($remaining > 0)
                            <div class="small text-muted">{{ $remaining }} parcela(s) restante(s)</div>
                        @else
                            <div class="small text-success"><i class="bi bi-check-circle-fill"></i> Quitado</div>
                        @endif
                    </td>
                    <td>
                        <span class="card-badge">
                            <span class="dot" style="background:{{ $purchase->creditCard->color }}"></span>
                            {{ $purchase->creditCard->name }}
                        </span>
                    </td>
                    <td class="text-muted small">
                        {{-- Data real no title, só mês/ano visível --}}
                        <span title="{{ \Carbon\Carbon::parse($purchase->purchased_at)->format('d/m/Y') }}">
                            {{ \Carbon\Carbon::parse($purchase->purchased_at)->translatedFormat('M/Y') }}
                        </span>
                    </td>
                    <td style="font-family:'DM Mono',monospace;font-size:13px;">
                        R${{ number_format($purchase->total_amount, 2, ',', '.') }}
                    </td>
                    <td>
                        <span class="badge bg-light text-dark border" style="font-size:12px;">
                            {{ $purchase->installments }}x
                        </span>
                    </td>
                    <td style="font-family:'DM Mono',monospace;font-size:13px;">
                        R${{ number_format($installmentValue, 2, ',', '.') }}
                    </td>
                    <td class="text-muted small">
                        {{ $lastInstallment->translatedFormat('M/Y') }}
                    </td>
                    <td class="text-end">
                        <a href="{{ route('purchases.edit', $purchase) }}"
                           class="btn btn-sm btn-outline-secondary me-1" title="Editar">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form action="{{ route('purchases.destroy', $purchase) }}"
                              method="POST" class="d-inline"
                              onsubmit="return confirm('Excluir esta compra?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center py-5 text-muted">
                        <i class="bi bi-credit-card fs-2 d-block mb-2 opacity-25"></i>
                        Nenhuma compra cadastrada ainda.
                        <a href="{{ route('purchases.create') }}" class="d-block mt-2 small">Adicionar primeira compra</a>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($purchases->hasPages())
    <div class="card-footer bg-white border-top py-3 px-4">
        {{ $purchases->links() }}
    </div>
    @endif
</div>

@endsection
