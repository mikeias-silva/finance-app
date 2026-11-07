{{--
    As caixinhas como o sistema as simula (depósitos + rendimento pelo CDI). O
    saldo real lá de cima traz o valor delas lido na importação: os dois lado a
    lado são a conferência.
--}}
@if($investmentData->isNotEmpty())
<section class="d2-section" aria-labelledby="d2-boxes-title">
    <div class="d2-section-head">
        <h2 class="d2-section-title" id="d2-boxes-title">Caixinhas</h2>
        <a href="{{ route('investments.index') }}" class="btn btn-sm btn-outline-secondary">Abrir investimentos</a>
    </div>

    <div class="d2-figures d2-figures-3">
        <div class="d2-figure">
            <div class="d2-figure-label">Total investido</div>
            <div class="d2-figure-value"><x-money :value="$totalInvested" /></div>
        </div>
        <div class="d2-figure">
            <div class="d2-figure-label">Rendimento acumulado</div>
            <div class="d2-figure-value"><x-money :value="$totalYield" signed tone /></div>
        </div>
        <div class="d2-figure">
            <div class="d2-figure-label">Total com rendimento</div>
            <div class="d2-figure-value"><x-money :value="$totalWithYield" /></div>
        </div>
    </div>

    <div class="d2-boxes">
        @foreach($investmentData as $item)
            @php $box = $item['box']; @endphp
            <div class="d2-box" style="--d2-box-color: {{ $box->color ?: 'var(--blue)' }};">
                <div class="d2-box-head">
                    <span class="d2-box-name">
                        <span class="d2-dot" aria-hidden="true"></span>{{ $box->name }}
                    </span>
                    <span class="d2-tag">{{ number_format($box->rate_percentage, 0) }}% do CDI</span>
                </div>
                <dl class="d2-box-values">
                    <div><dt>Saldo</dt><dd><x-money class="num" :value="$item['balance']" /></dd></div>
                    <div><dt>Rendimento</dt><dd><x-money class="num" :value="$item['yield']" signed tone /></dd></div>
                    <div><dt>Total</dt><dd><x-money class="num" :value="$item['total']" /></dd></div>
                </dl>
                @if($box->max_amount)
                    @php $used = min(100, (int) round($item['balance'] / $box->max_amount * 100)); @endphp
                    <div class="d2-box-capacity">
                        <div class="d-flex justify-content-between">
                            <span>Capacidade usada: {{ $used }}%</span>
                            <span><x-money :value="$box->max_amount - $item['balance']" /> livre</span>
                        </div>
                        <span class="d2-meter d2-meter-full" aria-hidden="true">
                            <span style="width: {{ $used }}%;"></span>
                        </span>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</section>
@endif
