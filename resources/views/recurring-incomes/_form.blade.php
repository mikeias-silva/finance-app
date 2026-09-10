{{-- Campos compartilhados entre criar e editar. $income é null na criação. --}}
@php $income ??= null; @endphp

<div class="mb-3">
    <label for="description" class="form-label">Descrição</label>
    <input type="text" id="description" name="description"
           class="form-control @error('description') is-invalid @enderror"
           value="{{ old('description', $income?->description) }}" required autofocus>
    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label for="amount" class="form-label">Valor por ciclo (R$)</label>
    <input type="number" id="amount" name="amount"
           class="form-control @error('amount') is-invalid @enderror"
           value="{{ old('amount', $income?->amount) }}" step="0.01" min="0.01" required>
    @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label for="effective_from" class="form-label">Primeiro pagamento</label>
    <input type="date" id="effective_from" name="effective_from"
           class="form-control @error('effective_from') is-invalid @enderror"
           value="{{ old('effective_from', $income?->effective_from?->format('Y-m-d')) }}" required>
    <div class="form-text">
        Use a data em que o valor cai na conta — o último dia útil do mês. Ela define
        o ciclo em que a receita estreia.
    </div>
    @error('effective_from')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-4">
    <label for="valid_until" class="form-label">Último pagamento <span class="text-muted fw-normal">(opcional)</span></label>
    <input type="date" id="valid_until" name="valid_until"
           class="form-control @error('valid_until') is-invalid @enderror"
           value="{{ old('valid_until', $income?->valid_until?->format('Y-m-d')) }}">
    <div class="form-text">Em branco, a receita não tem prazo para acabar.</div>
    @error('valid_until')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="form-check form-switch mb-4">
    <input type="hidden" name="is_active" value="0">
    <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"
           @checked(old('is_active', $income?->is_active ?? true))>
    <label class="form-check-label" for="is_active">Receita ativa</label>
</div>
