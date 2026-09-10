{{--
    Dispara a mesma importação do comando `import:pluggy-nubank`.

    O botão da esquerda roda o padrão (ciclo salarial atual até hoje) num clique;
    o da direita abre o modal com as opções equivalentes às flags do comando.
--}}
<div class="btn-group btn-group-sm" role="group" aria-label="Importar Nubank">
    <form method="POST" action="{{ route('imports.nubank') }}" data-import-form class="d-inline">
        @csrf
        <button type="submit" class="btn btn-primary d-flex align-items-center gap-2" data-import-submit>
            <i class="bi bi-arrow-clockwise" data-import-icon></i>
            <span>Atualizar Nubank</span>
        </button>
    </form>
    <button type="button"
            class="btn btn-outline-secondary"
            data-bs-toggle="modal"
            data-bs-target="#nubankImportModal"
            title="Opções da importação"
            aria-label="Opções da importação">
        <i class="bi bi-sliders"></i>
    </button>
</div>

<div class="modal fade" id="nubankImportModal" tabindex="-1" aria-labelledby="nubankImportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="{{ route('imports.nubank') }}" data-import-form class="modal-content">
            @csrf

            <div class="modal-header">
                <h2 class="modal-title h6 mb-0" id="nubankImportModalLabel">Importar movimentação do Nubank</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>

            <div class="modal-body">
                @if($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0 ps-3">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <p class="text-muted small">
                    Sem datas, importa do início do ciclo salarial atual até hoje —
                    o mesmo padrão do comando <code>import:pluggy-nubank</code>.
                </p>

                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label for="importFrom" class="form-label">De</label>
                        <input type="date" id="importFrom" name="from" value="{{ old('from') }}" class="form-control">
                    </div>
                    <div class="col-6">
                        <label for="importTo" class="form-label">Até</label>
                        <input type="date" id="importTo" name="to" value="{{ old('to') }}" class="form-control">
                    </div>
                </div>

                <div class="form-check mb-3">
                    <input type="checkbox" id="importDryRun" name="dry_run" value="1"
                           class="form-check-input" {{ old('dry_run') ? 'checked' : '' }}>
                    <label for="importDryRun" class="form-check-label">
                        Apenas simular
                        <span class="text-muted d-block small">Mostra o que seria gravado, sem alterar nada.</span>
                    </label>
                </div>

                <div class="form-check">
                    <input type="checkbox" id="importReplaceInvestments" name="replace_investments" value="1"
                           class="form-check-input" data-import-replace {{ old('replace_investments') ? 'checked' : '' }}>
                    <label for="importReplaceInvestments" class="form-check-label">
                        Regravar caixinhas
                        <span class="text-muted d-block small">Substitui as movimentações pelas posições reais da API.</span>
                    </label>
                </div>

                <div class="alert alert-danger mt-3 mb-0 {{ old('replace_investments') ? '' : 'd-none' }}"
                     data-import-replace-warning>
                    <div class="d-flex align-items-start gap-2">
                        <i class="bi bi-exclamation-triangle-fill mt-1"></i>
                        <div>
                            <strong>Isto apaga todas as movimentações de investimento</strong> antes de regravar.
                            O que não vier da API do Pluggy é perdido.
                            <div class="form-check mt-2">
                                <input type="checkbox" id="importConfirmReplace" name="confirm_replace" value="1"
                                       class="form-check-input">
                                <label for="importConfirmReplace" class="form-check-label">
                                    Entendo e quero regravar as caixinhas
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary d-flex align-items-center gap-2" data-import-submit>
                    <i class="bi bi-arrow-clockwise" data-import-icon></i>
                    <span>Importar</span>
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    (() => {
        // A importação é síncrona e leva dezenas de segundos. Sem travar o botão
        // e mostrar que algo acontece, o usuário clica de novo.
        document.querySelectorAll('[data-import-form]').forEach((form) => {
            form.addEventListener('submit', () => {
                form.querySelectorAll('[data-import-submit]').forEach((button) => {
                    button.disabled = true;
                    const icon = button.querySelector('[data-import-icon]');
                    if (icon) {
                        icon.className = 'spinner-border spinner-border-sm';
                    }
                });
            });
        });

        const replace = document.querySelector('[data-import-replace]');
        const warning = document.querySelector('[data-import-replace-warning]');

        if (replace && warning) {
            replace.addEventListener('change', () => {
                warning.classList.toggle('d-none', !replace.checked);

                if (!replace.checked) {
                    warning.querySelector('#importConfirmReplace').checked = false;
                }
            });
        }

        // Erro de validação vem do formulário do modal, que já fechou no submit:
        // reabre para o usuário ver a mensagem e corrigir.
        @if($errors->any())
            new bootstrap.Modal(document.getElementById('nubankImportModal')).show();
        @endif
    })();
</script>
@endpush
