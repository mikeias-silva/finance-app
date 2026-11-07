{{--
    Valor em reais num formato só: R$ 1.234,56. O sinal de menos nunca some;
    `signed` acrescenta o + aos positivos (resultados, variações) e `tone`
    pinta pelo sinal. Saldos e totais vão sem os dois.
--}}
@props(['value', 'signed' => false, 'tone' => false])
@php
    $amount = round((float) $value, 2);
    $sign = $amount < 0 ? '−' : ($signed && $amount > 0 ? '+' : '');
@endphp
<span {{ $attributes->class(['money', 'positive' => $tone && $amount > 0, 'negative' => $tone && $amount < 0]) }}>{{ $sign }}R$ {{ number_format(abs($amount), 2, ',', '.') }}</span>
