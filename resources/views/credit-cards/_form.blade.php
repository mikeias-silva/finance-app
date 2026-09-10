<div class="mb-3">
    <label for="name" class="form-label">Nome</label>
    <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
           value="{{ old('name', $card->name ?? '') }}" placeholder="Ex: Nubank - Mastercard" required autofocus>
    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="row g-3 mb-3">
    <div class="col-6">
        <label for="color" class="form-label">Cor</label>
        <input type="color" id="color" name="color" class="form-control form-control-color @error('color') is-invalid @enderror"
               value="{{ old('color', $card->color ?? '#6366f1') }}" title="Escolha a cor" required>
        @error('color')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6">
        <label for="closing_day" class="form-label">Dia de fechamento</label>
        <input type="number" id="closing_day" name="closing_day" min="1" max="31"
               class="form-control @error('closing_day') is-invalid @enderror"
               value="{{ old('closing_day', $card->closing_day ?? '') }}" required>
        @error('closing_day')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-6">
        <label for="due_day" class="form-label">Dia de vencimento <span class="text-muted fw-normal">(opcional)</span></label>
        <input type="number" id="due_day" name="due_day" min="1" max="31"
               class="form-control @error('due_day') is-invalid @enderror"
               value="{{ old('due_day', $card->due_day ?? '') }}">
        @error('due_day')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

<div class="form-check form-switch mb-4">
    <input type="hidden" name="is_active" value="0">
    <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"
           @checked((string) old('is_active', ($card->is_active ?? true) ? '1' : '0') === '1')>
    <label class="form-check-label" for="is_active">Cartão ativo</label>
</div>

<div class="accordion mb-4" id="advancedAccordion">
    <div class="accordion-item">
        <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#advancedFields">
                Avançado — mudança de fechamento/vencimento e faturas puladas
            </button>
        </h2>
        <div id="advancedFields" class="accordion-collapse collapse" data-bs-parent="#advancedAccordion">
            <div class="accordion-body">
                <p class="text-muted small">Preencha apenas se o dia de fechamento ou vencimento deste cartão já mudou no passado.</p>

                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label for="old_closing_day" class="form-label">Fechamento antigo</label>
                        <input type="number" id="old_closing_day" name="old_closing_day" min="1" max="31"
                               class="form-control @error('old_closing_day') is-invalid @enderror"
                               value="{{ old('old_closing_day', $card->old_closing_day ?? '') }}">
                        @error('old_closing_day')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-6">
                        <label for="old_due_day" class="form-label">Vencimento antigo</label>
                        <input type="number" id="old_due_day" name="old_due_day" min="1" max="31"
                               class="form-control @error('old_due_day') is-invalid @enderror"
                               value="{{ old('old_due_day', $card->old_due_day ?? '') }}">
                        @error('old_due_day')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="mb-3">
                    <label for="closing_changed_at" class="form-label">Data da mudança</label>
                    <input type="date" id="closing_changed_at" name="closing_changed_at"
                           class="form-control @error('closing_changed_at') is-invalid @enderror"
                           value="{{ old('closing_changed_at', optional($card->closing_changed_at ?? null)->format('Y-m-d')) }}">
                    @error('closing_changed_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-1">
                    <label for="billing_skip_months" class="form-label">Meses sem fatura</label>
                    <input type="text" id="billing_skip_months" name="billing_skip_months"
                           class="form-control @error('billing_skip_months') is-invalid @enderror"
                           value="{{ old('billing_skip_months', is_array($card->billing_skip_months ?? null) ? implode(', ', $card->billing_skip_months) : '') }}"
                           placeholder="Ex: 2026-07, 2026-08">
                    <div class="form-text">Separe por vírgula, no formato AAAA-MM.</div>
                    @error('billing_skip_months')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </div>
</div>
