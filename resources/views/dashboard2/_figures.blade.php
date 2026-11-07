{{--
    Quatro pontos da mesma cadeia: o saldo lido, o fim do primeiro ciclo, o
    fundo do poço e o fim do horizonte. O primeiro diz de quando é, porque é
    dele que todo o resto parte.
--}}
@php
    $first = $cycles->first();
    $syncedAt = $balanceSnapshot?->synced_at;
    $daysOld = $syncedAt ? (int) $syncedAt->copy()->startOfDay()->diffInDays(today()) : null;
    $age = match (true) {
        $daysOld === null, $daysOld === 0 => '',
        $daysOld === 1 => ', ontem',
        default => ", há {$daysOld} dias",
    };
@endphp

<div class="d2-figures">
    <div class="d2-figure">
        <div class="d2-figure-label">Saldo real</div>
        <div class="d2-figure-value"><x-money :value="$anchor" :class="$anchor < 0 ? 'negative' : ''" /></div>
        <div class="d2-figure-note">
            @if($balanceSnapshot)
                Conta <x-money :value="$balanceSnapshot->checking" /> e caixinhas <x-money :value="$balanceSnapshot->boxes" />
                <span @class(['d-block', 'd2-stale' => $daysOld > 0])>
                    @if($daysOld > 0)<i class="bi bi-exclamation-triangle-fill me-1" aria-hidden="true"></i>@endif
                    Lido em {{ $syncedAt->format('d/m \à\s H:i') }}{{ $age }}
                </span>
            @else
                <span class="d-block d2-stale">
                    <i class="bi bi-exclamation-triangle-fill me-1" aria-hidden="true"></i>Saldo real nunca importado
                </span>
                Usando o total simulado das caixinhas
            @endif
        </div>
    </div>

    <div class="d2-figure">
        <div class="d2-figure-label">Fim de {{ $first['month']->translatedFormat('F') }}</div>
        <div class="d2-figure-value"><x-money :value="$first['accumulated']" :class="$first['accumulated'] < 0 ? 'negative' : ''" /></div>
        <div class="d2-figure-note">
            {{-- Do ciclo em curso só entra o que ainda não aconteceu: é este número que
                 liga o saldo lido ao saldo do fim do ciclo. --}}
            Falta acontecer <x-money :value="$first['pending']" signed />
            <span class="d-block">Ciclo de {{ $first['period_start']->format('d/m') }} a {{ $first['period_end']->format('d/m') }}</span>
        </div>
    </div>

    <div class="d2-figure">
        <div class="d2-figure-label">Menor saldo</div>
        <div class="d2-figure-value"><x-money :value="$lowest['value']" :class="$lowest['value'] < 0 ? 'negative' : ''" /></div>
        <div class="d2-figure-note">
            No fim de {{ $lowest['month']->translatedFormat('F \d\e Y') }}
        </div>
    </div>

    <div class="d2-figure">
        <div class="d2-figure-label">Fim do horizonte</div>
        <div class="d2-figure-value"><x-money :value="$horizonEnd['value']" :class="$horizonEnd['value'] < 0 ? 'negative' : ''" /></div>
        <div class="d2-figure-note">
            {{ ucfirst($horizonEnd['month']->translatedFormat('F \d\e Y')) }}
            <span class="d-block"><x-money :value="$horizonEnd['value'] - $anchor" signed tone /> sobre o saldo real</span>
        </div>
    </div>
</div>
