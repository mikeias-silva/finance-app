@extends('layouts.app')

@section('title', 'Editar compra')

@push('styles')
<style>
    .preview-installment {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 6px 0;
        border-bottom: 1px solid #f1f5f9;
        font-size: 13px;
    }
    .preview-installment:last-child { border-bottom: none; }
</style>
@endpush

@section('content')

<div class="d-flex align-items-center gap-3 mb-4">
    <a href="{{ route('purchases.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i>
    </a>
    <div>
        <h1 class="h5 fw-semibold mb-0">Editar compra</h1>
        <p class="text-muted small mb-0 mt-1">{{ $purchase->description }}</p>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-body p-4">
                <form action="{{ route('purchases.update', $purchase) }}" method="POST" id="purchase-form">
                    @csrf @method('PUT')

                    <div class="mb-3">
                        <label class="form-label" for="description">Descrição</label>
                        <input type="text"
                               id="description"
                               name="description"
                               class="form-control @error('description') is-invalid @enderror"
                               value="{{ old('description', $purchase->description) }}"
                               required autofocus>
                        @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="credit_card_id">Cartão de crédito</label>
                        <select id="credit_card_id" name="credit_card_id"
                                class="form-select @error('credit_card_id') is-invalid @enderror" required>
                            <option value="">Selecione...</option>
                            @foreach($cards as $card)
                                <option value="{{ $card->id }}"
                                        data-color="{{ $card->color }}"
                                        {{ old('credit_card_id', $purchase->credit_card_id) == $card->id ? 'selected' : '' }}>
                                    {{ $card->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('credit_card_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label" for="total_amount">Valor total (R$)</label>
                            <input type="number"
                                   id="total_amount"
                                   name="total_amount"
                                   class="form-control @error('total_amount') is-invalid @enderror"
                                   value="{{ old('total_amount', $purchase->total_amount) }}"
                                   step="0.01" min="0.01" required>
                            @error('total_amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="installments">Parcelas</label>
                            <div class="input-group">
                                <input type="number"
                                       id="installments"
                                       name="installments"
                                       class="form-control @error('installments') is-invalid @enderror"
                                       value="{{ old('installments', $purchase->installments) }}"
                                       min="1" max="60" required>
                                <span class="input-group-text text-muted">x</span>
                            </div>
                            @error('installments') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label" for="purchased_at">
                            Data da compra
                            <span class="text-muted fw-normal">(exibido como mês/ano)</span>
                        </label>
                        <input type="date"
                               id="purchased_at"
                               name="purchased_at"
                               class="form-control @error('purchased_at') is-invalid @enderror"
                               value="{{ old('purchased_at', \Carbon\Carbon::parse($purchase->purchased_at)->format('Y-m-d')) }}"
                               required>
                        @error('purchased_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-check-lg me-1"></i> Salvar alterações
                        </button>
                        <a href="{{ route('purchases.index') }}" class="btn btn-outline-secondary">
                            Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-calendar3 me-2 text-muted"></i>Preview das parcelas
            </div>
            <div class="card-body">
                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:16px;">
                    <div id="preview-empty" class="text-muted text-center small py-2">
                        Atualize os campos para visualizar.
                    </div>
                    <div id="preview-list" class="d-none"></div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    const totalInput   = document.getElementById('total_amount');
    const installInput = document.getElementById('installments');
    const dateInput    = document.getElementById('purchased_at');
    const previewList  = document.getElementById('preview-list');
    const previewEmpty = document.getElementById('preview-empty');
    const MONTHS_PT    = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];

    function updatePreview() {
        const total    = parseFloat(totalInput.value);
        const parcelas = parseInt(installInput.value);
        const dateVal  = dateInput.value;
        if (!total || !parcelas || !dateVal || isNaN(total) || isNaN(parcelas)) {
            previewList.classList.add('d-none');
            previewEmpty.classList.remove('d-none');
            return;
        }
        const val  = total / parcelas;
        const start = new Date(dateVal + 'T12:00:00');
        let html = '';
        for (let i = 0; i < Math.min(parcelas, 24); i++) {
            const d = new Date(start);
            d.setMonth(d.getMonth() + i);
            const label = `${MONTHS_PT[d.getMonth()]}/${d.getFullYear()}`;
            html += `<div class="preview-installment">
                <span class="text-muted">${i+1}/${parcelas} · ${label}</span>
                <span style="font-family:'DM Mono',monospace;font-weight:600;">
                    R$ ${val.toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2})}
                </span>
            </div>`;
        }
        previewList.innerHTML = html;
        previewList.classList.remove('d-none');
        previewEmpty.classList.add('d-none');
    }

    totalInput.addEventListener('input', updatePreview);
    installInput.addEventListener('input', updatePreview);
    dateInput.addEventListener('change', updatePreview);
    updatePreview();
</script>
@endpush
