@extends('layouts.app')

@section('title', 'Nova compra')

@push('styles')
<style>
    .preview-box {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 16px;
    }
    .preview-installment {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 6px 0;
        border-bottom: 1px solid #f1f5f9;
        font-size: 13px;
    }
    .preview-installment:last-child { border-bottom: none; }
    .preview-empty {
        color: #94a3b8;
        font-size: 13px;
        text-align: center;
        padding: 12px 0;
    }
</style>
@endpush

@section('content')

<div class="d-flex align-items-center gap-3 mb-4">
    <a href="{{ route('purchases.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i>
    </a>
    <div>
        <h1 class="h5 fw-semibold mb-0">Nova compra parcelada</h1>
        <p class="text-muted small mb-0 mt-1">Preencha os dados da compra</p>
    </div>
</div>

<div class="row g-4">

    {{-- Formulário --}}
    <div class="col-lg-7">
        <div class="card">
            <div class="card-body p-4">
                <form action="{{ route('purchases.store') }}" method="POST" id="purchase-form">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label" for="description">Descrição</label>
                        <input type="text"
                               id="description"
                               name="description"
                               class="form-control @error('description') is-invalid @enderror"
                               value="{{ old('description') }}"
                               placeholder="Ex: TV Samsung, Notebook Dell..."
                               required autofocus>
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="credit_card_id">Cartão de crédito</label>
                        <select id="credit_card_id"
                                name="credit_card_id"
                                class="form-select @error('credit_card_id') is-invalid @enderror"
                                required>
                            <option value="">Selecione o cartão...</option>
                            @foreach($cards as $card)
                                <option value="{{ $card->id }}"
                                        data-color="{{ $card->color }}"
                                        {{ old('credit_card_id') == $card->id ? 'selected' : '' }}>
                                    {{ $card->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('credit_card_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        @if($cards->isEmpty())
                            <div class="form-text text-warning">
                                <i class="bi bi-exclamation-triangle"></i>
                                Nenhum cartão cadastrado.
                                <a href="{{ route('credit-cards.create') }}">Cadastrar agora</a>
                            </div>
                        @endif
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label" for="total_amount">Valor total (R$)</label>
                            <input type="number"
                                   id="total_amount"
                                   name="total_amount"
                                   class="form-control @error('total_amount') is-invalid @enderror"
                                   value="{{ old('total_amount') }}"
                                   placeholder="0,00"
                                   step="0.01" min="0.01"
                                   required>
                            @error('total_amount')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="installments">Parcelas</label>
                            <div class="input-group">
                                <input type="number"
                                       id="installments"
                                       name="installments"
                                       class="form-control @error('installments') is-invalid @enderror"
                                       value="{{ old('installments', 1) }}"
                                       min="1" max="60"
                                       required>
                                <span class="input-group-text text-muted">x</span>
                            </div>
                            @error('installments')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
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
                               value="{{ old('purchased_at', now()->format('Y-m-d')) }}"
                               required>
                        @error('purchased_at')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">A 1ª parcela cai na fatura do mês da compra.</div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-check-lg me-1"></i> Salvar compra
                        </button>
                        <a href="{{ route('purchases.index') }}" class="btn btn-outline-secondary">
                            Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Preview de parcelas --}}
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-calendar3 me-2 text-muted"></i>
                Preview das parcelas
            </div>
            <div class="card-body">
                <div class="preview-box" id="preview-box">
                    <div class="preview-empty" id="preview-empty">
                        Preencha o valor e as parcelas para visualizar.
                    </div>
                    <div id="preview-list" class="d-none"></div>
                </div>
                <div class="mt-3 d-flex justify-content-between small text-muted" id="preview-summary" style="display:none!important">
                    <span>Total:</span>
                    <span id="preview-total" style="font-family:'DM Mono',monospace;font-weight:600;color:var(--text-primary)"></span>
                </div>
            </div>
        </div>
    </div>

</div>

@endsection

@push('scripts')
<script>
    const totalInput    = document.getElementById('total_amount');
    const installInput  = document.getElementById('installments');
    const dateInput     = document.getElementById('purchased_at');
    const previewList   = document.getElementById('preview-list');
    const previewEmpty  = document.getElementById('preview-empty');
    const previewSummary = document.getElementById('preview-summary');
    const previewTotal  = document.getElementById('preview-total');

    const MONTHS_PT = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];

    function updatePreview() {
        const total    = parseFloat(totalInput.value);
        const parcelas = parseInt(installInput.value);
        const dateVal  = dateInput.value;

        if (!total || !parcelas || !dateVal || isNaN(total) || isNaN(parcelas) || parcelas < 1) {
            previewList.classList.add('d-none');
            previewEmpty.classList.remove('d-none');
            previewSummary.style.setProperty('display','none','important');
            return;
        }

        const installmentValue = total / parcelas;
        const startDate = new Date(dateVal + 'T12:00:00');

        let html = '';
        for (let i = 0; i < Math.min(parcelas, 24); i++) {
            const d = new Date(startDate);
            d.setMonth(d.getMonth() + i);
            const label = `${MONTHS_PT[d.getMonth()]}/${d.getFullYear()}`;
            html += `
                <div class="preview-installment">
                    <span class="text-muted">${i + 1}/${parcelas} &nbsp;·&nbsp; ${label}</span>
                    <span style="font-family:'DM Mono',monospace;font-weight:600;">
                        R$ ${installmentValue.toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2})}
                    </span>
                </div>`;
        }
        if (parcelas > 24) {
            html += `<div class="preview-empty">+ ${parcelas - 24} parcelas omitidas...</div>`;
        }

        previewList.innerHTML = html;
        previewList.classList.remove('d-none');
        previewEmpty.classList.add('d-none');

        previewTotal.textContent = 'R$ ' + total.toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2});
        previewSummary.style.removeProperty('display');
    }

    totalInput.addEventListener('input', updatePreview);
    installInput.addEventListener('input', updatePreview);
    dateInput.addEventListener('change', updatePreview);

    // Roda no carregamento (se vier com old values)
    updatePreview();
</script>
@endpush
