{{--
    A trajetória do saldo em conta: parte do saldo lido e passa pelo fim de cada
    ciclo. Uma linha só, verde acima de zero e vermelha abaixo, com a área até o
    zero. O SVG desenha só traço e área (esticado na largura); pontos, rótulos e
    escala são HTML para não deformarem. Valores arredondados aqui; o exato fica
    no título de cada ponto e na tabela logo abaixo.
--}}
@php
    $points = collect([[
        'key' => null,
        'label' => 'Saldo',
        'sublabel' => $balanceSnapshot ? $balanceSnapshot->synced_at->format('d/m') : 'estimado',
        'value' => (float) $anchor,
        'title' => 'Saldo real'.($balanceSnapshot ? ' lido em '.$balanceSnapshot->synced_at->format('d/m \à\s H:i') : ' estimado pelas caixinhas'),
        'is_current' => false,
    ]])->concat($cycles->map(fn (array $cycle, string $key) => [
        'key' => $key,
        'label' => ucfirst($cycle['month']->translatedFormat('M')),
        'sublabel' => $cycle['is_current'] ? 'atual' : $cycle['month']->format('Y'),
        'value' => (float) $cycle['accumulated'],
        'title' => ucfirst($cycle['month']->translatedFormat('F \d\e Y'))
            .': saldo em conta de R$ '.number_format($cycle['accumulated'], 2, ',', '.')
            .' em '.$cycle['period_end']->format('d/m'),
        'is_current' => $cycle['is_current'],
    ])->values());

    $count = $points->count();
    $values = $points->pluck('value');

    // Escala com o zero sempre visível e um passo "redondo" (1, 2, 2,5 ou 5 vezes
    // uma potência de dez) para umas quatro faixas. Sem variação nenhuma — tudo
    // zero —, vale uma faixa de mil para a conta não dividir por zero.
    $low = min(0.0, (float) $values->min());
    $high = max(0.0, (float) $values->max());
    $range = ($high - $low) ?: 1000.0;
    $rough = $range / 4;
    $power = 10 ** floor(log10($rough));
    $step = collect([1, 2, 2.5, 5, 10])->map(fn ($m) => $m * $power)->first(fn ($s) => $s >= $rough);

    // Folga acima e abaixo para os rótulos dos pontos não baterem na borda.
    $top = ceil(($high + $range * 0.18) / $step) * $step;
    $bottom = $low < 0 ? floor(($low - $range * 0.18) / $step) * $step : 0.0;
    $ticks = collect(range(0, (int) round(($top - $bottom) / $step)))->map(fn ($i) => $bottom + $i * $step);

    // Coordenadas em milésimos da área: x da esquerda para a direita, y de cima para baixo.
    $xOf = fn (int $i) => $count === 1 ? 500.0 : $i * 1000 / ($count - 1);
    $yOf = fn (float $value) => ($top - $value) / ($top - $bottom) * 1000;
    $zeroY = $yOf(0.0);
    $band = $count === 1 ? 100 : 100 / ($count - 1); // largura da faixa clicável, em %

    $tickLabel = function (float $tick): string {
        if ($tick == 0) {
            return '0';
        }
        $sign = $tick < 0 ? '−' : '';
        $abs = abs($tick);

        return $abs >= 1000
            ? $sign.str_replace('.', ',', rtrim(rtrim(number_format($abs / 1000, 1, '.', ''), '0'), '.')).' mil'
            : $sign.number_format($abs, 0, ',', '.');
    };

    $coordinates = $points->map(fn (array $point, int $i) => round($xOf($i), 2).' '.round($yOf($point['value']), 2));
    $line = 'M '.$coordinates->implode(' L ');
    $area = 'M '.round($xOf(0), 2).' '.round($zeroY, 2).' L '.$coordinates->implode(' L ')
        .' L '.round($xOf($count - 1), 2).' '.round($zeroY, 2).' Z';

    $lowestKey = $lowest['month']->format('Y-m');
@endphp

<div class="d2-line" style="--d2-n: {{ $count }};">
    <div class="d2-line-scroll">
        <div class="d2-line-inner">
            <div class="d2-line-y" aria-hidden="true">
                @foreach($ticks as $tick)
                    <span style="top: {{ round($yOf($tick) / 10, 3) }}%;">{{ $tickLabel($tick) }}</span>
                @endforeach
            </div>

            <div class="d2-line-plot">
                @foreach($ticks as $tick)
                    <div @class(['d2-grid', 'is-zero' => $tick == 0]) style="top: {{ round($yOf($tick) / 10, 3) }}%;"></div>
                @endforeach

                <div class="d2-line-area">
                    <svg viewBox="0 0 1000 1000" preserveAspectRatio="none" aria-hidden="true" focusable="false">
                        <defs>
                            {{-- Corte seco no zero: acima é sobra, abaixo é falta. --}}
                            <linearGradient id="d2-sign" gradientUnits="userSpaceOnUse" x1="0" y1="0" x2="0" y2="1000">
                                <stop offset="{{ round($zeroY / 1000, 4) }}" style="stop-color: var(--green);" />
                                <stop offset="{{ round($zeroY / 1000, 4) }}" style="stop-color: var(--red);" />
                            </linearGradient>
                        </defs>
                        <path d="{{ $area }}" fill="url(#d2-sign)" fill-opacity=".12" />
                        <path d="{{ $line }}" fill="none" stroke="url(#d2-sign)" stroke-width="2"
                              stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
                    </svg>

                    @foreach($points as $i => $point)
                        @php
                            $value = $point['value'];
                            $x = round($xOf($i) / 10, 3);
                            $y = round($yOf($value) / 10, 3);

                            // O rótulo vai para o quadrante que a linha não ocupa: em cima de um
                            // pico, embaixo de um vale e na diagonal livre de uma encosta. Perto
                            // da borda, troca para o lado de dentro.
                            $prev = $values[$i - 1] ?? null;
                            $next = $values[$i + 1] ?? null;
                            $place = match (true) {
                                $prev === null => $next === null || $next <= $value ? 'top' : 'bottom',
                                $next === null => $prev <= $value ? 'top' : 'bottom',
                                $value >= $prev && $value >= $next => 'top',
                                $value <= $prev && $value <= $next => 'bottom',
                                $prev < $value => 'top-left',   // subindo: livres o alto à esquerda e o baixo à direita
                                default => 'top-right',         // descendo: livres o alto à direita e o baixo à esquerda
                            };
                            if ($y < 14) {
                                $place = ['top' => 'bottom', 'top-left' => 'bottom-right', 'top-right' => 'bottom-left'][$place] ?? $place;
                            } elseif ($y > 86) {
                                $place = ['bottom' => 'top', 'bottom-right' => 'top-left', 'bottom-left' => 'top-right'][$place] ?? $place;
                            }

                            // No celular só cabem os rótulos que contam a história: o ponto
                            // de partida, o ciclo atual, o fundo do poço e o fim.
                            $isKey = $i <= 1 || $i === $count - 1 || $point['key'] === $lowestKey;
                        @endphp

                        @if($point['key'])
                            <button type="button" class="d2-hit" style="left: {{ $x }}%; width: {{ round($band, 3) }}%;"
                                    data-cycle="{{ $point['key'] }}" data-cycle-open="{{ $point['key'] }}"
                                    title="{{ $point['title'] }}" aria-label="{{ $point['title'] }}"></button>
                        @endif
                        <span @class([
                                'd2-point',
                                'is-anchor' => $point['key'] === null,
                                'is-current' => $point['is_current'],
                                'negative' => $value < 0,
                              ])
                              style="left: {{ $x }}%; top: {{ $y }}%;"
                              @if($point['key']) data-cycle="{{ $point['key'] }}" @else title="{{ $point['title'] }}" @endif></span>
                        <span @class([
                                'd2-point-label',
                                'at-'.$place,
                                'is-negative' => $value < 0,
                                'is-anchor' => $point['key'] === null,
                                'is-strong' => $point['key'] === $lowestKey || $point['is_current'],
                                'is-minor' => ! $isKey,
                              ])
                              style="left: {{ $x }}%; top: {{ $y }}%;" aria-hidden="true">{{ $value < 0 ? '−' : '' }}R$ {{ number_format(abs($value), 0, ',', '.') }}</span>
                    @endforeach
                </div>
            </div>

            <div class="d2-line-x" aria-hidden="true">
                <div class="d2-line-x-inner">
                    @foreach($points as $i => $point)
                        <span @class(['d2-x', 'is-current' => $point['is_current'], 'is-anchor' => $point['key'] === null])
                              style="left: {{ round($xOf($i) / 10, 3) }}%;"
                              @if($point['key']) data-cycle="{{ $point['key'] }}" @endif>
                            {{ $point['label'] }}
                            {{-- No celular os rótulos ficam a ~40px um do outro: "lido" sai para caber. --}}
                            <small>@if($point['key'] === null && $balanceSnapshot)<span class="d2-x-long">lido </span>@endif{{ $point['sublabel'] }}</small>
                        </span>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
