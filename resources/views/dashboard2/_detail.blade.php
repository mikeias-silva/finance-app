{{--
    O detalhado de cada ciclo, na própria página: uma aba por mês, aberta no
    primeiro. Tudo é renderizado aqui, com escape — as descrições vêm do extrato.
--}}
<section class="d2-section" id="d2-detail" aria-labelledby="d2-detail-title">
    <div class="d2-section-head">
        <h2 class="d2-section-title" id="d2-detail-title">Detalhe do ciclo</h2>
        <span class="d2-section-note">Clique num mês do gráfico ou da tabela para abrir aqui</span>
    </div>

    <div class="nav d2-tabs" role="tablist" aria-label="Ciclos">
        @foreach($cycles as $key => $cycle)
            <button type="button"
                    @class(['nav-link', 'd2-tab', 'active' => $loop->first])
                    id="d2-tab-{{ $key }}"
                    data-bs-toggle="pill"
                    data-bs-target="#d2-pane-{{ $key }}"
                    data-cycle-tab="{{ $key }}"
                    role="tab"
                    aria-controls="d2-pane-{{ $key }}"
                    aria-selected="{{ $loop->first ? 'true' : 'false' }}">
                {{ ucfirst($cycle['month']->translatedFormat('M/y')) }}
            </button>
        @endforeach
    </div>

    <div class="tab-content">
        @foreach($cycles as $key => $cycle)
            <div @class(['tab-pane', 'd2-pane', 'show active' => $loop->first])
                 id="d2-pane-{{ $key }}"
                 role="tabpanel"
                 aria-labelledby="d2-tab-{{ $key }}"
                 tabindex="0">
                @include('dashboard2._cycle', ['cycle' => $cycle, 'isFirst' => $loop->first])
            </div>
        @endforeach
    </div>
</section>
