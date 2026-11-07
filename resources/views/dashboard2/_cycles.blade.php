{{--
    Um ciclo por linha, para comparar descendo a coluna. "Resultado do ciclo" é
    receitas menos despesas; "Saldo em conta" é a cadeia acumulada. No primeiro
    ciclo os dois se separam: dele só entra no saldo o que ainda falta acontecer.
--}}
<div class="table-responsive">
    <table class="table d2-cycles mb-0">
        <thead>
            <tr>
                <th scope="col">Ciclo</th>
                <th scope="col" class="text-end">Receitas</th>
                <th scope="col" class="text-end">Despesas</th>
                <th scope="col">Comprometido</th>
                <th scope="col" class="text-end">Resultado do ciclo</th>
                <th scope="col" class="text-end">Saldo em conta</th>
            </tr>
        </thead>
        <tbody>
            @foreach($cycles as $key => $cycle)
                @php
                    $committed = $cycle['committed'] === null ? null : (int) round($cycle['committed'] * 100);
                @endphp
                <tr data-cycle="{{ $key }}">
                    <td class="d2-c-cycle">
                        <button type="button" class="d2-cycle-link" data-cycle-open="{{ $key }}">
                            {{ ucfirst($cycle['month']->translatedFormat('F \d\e Y')) }}
                        </button>
                        @if($cycle['is_current'])
                            <span class="d2-pill">atual</span>
                        @endif
                        <span class="d2-cell-note d-block">
                            {{ $cycle['period_start']->format('d/m') }} a {{ $cycle['period_end']->format('d/m') }}
                        </span>
                    </td>
                    {{-- data-label vira o rótulo de cada valor quando a tabela empilha no celular. --}}
                    <td class="text-end num d2-c-income" data-label="Receitas"><x-money :value="$cycle['income']" /></td>
                    <td class="text-end num d2-c-expenses" data-label="Despesas"><x-money :value="$cycle['expenses']" /></td>
                    <td class="d2-c-committed" data-label="Comprometido">
                        @if($committed === null)
                            <span class="muted" title="Ciclo sem receita">—</span>
                        @else
                            <span class="d2-committed">
                                <span @class(['d2-meter', 'is-over' => $committed > 100]) aria-hidden="true">
                                    <span style="width: {{ min(100, $committed) }}%;"></span>
                                </span>
                                <span @class(['num', 'negative' => $committed > 100])>{{ $committed }}%</span>
                            </span>
                        @endif
                    </td>
                    <td class="text-end num d2-c-result" data-label="Resultado"><x-money :value="$cycle['balance']" signed tone /></td>
                    <td class="text-end num d2-c-balance" data-label="Saldo em conta">
                        <x-money :value="$cycle['accumulated']" :class="$cycle['accumulated'] < 0 ? 'negative' : ''" />
                        @if($loop->first)
                            <span class="d2-cell-note d-block">falta acontecer <x-money :value="$cycle['pending']" signed /></span>
                        @endif
                        @if($cycle['yield'] > 0)
                            <span class="d2-cell-note d-block">rendimento <x-money :value="$cycle['yield']" signed /></span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
