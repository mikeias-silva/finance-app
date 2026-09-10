<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Identifica cobranças recorrentes (assinaturas) nos lançamentos de cartão.
 *
 * A API do Pluggy tem um endpoint para isso (POST /recurring-payments na
 * enrichment-api), mas ele depende da feature de Categorization e retorna vazio
 * nesta conta, então a detecção é feita aqui com os mesmos critérios: no mínimo
 * três ocorrências, cadência mensal e valor estável.
 *
 * O agrupamento é por cartão + valor + dia do mês, não por descrição: a mesma
 * assinatura chega com textos diferentes a cada mês ("Wellhub Mikeias Silva Gom",
 * "Wellhub", "Wellhub Gympass BR Wellhu"), enquanto cartão, valor e dia se mantêm.
 */
class RecurringChargeDetector
{
    private const MIN_OCCURRENCES = 3;
    private const DAY_TOLERANCE = 5;

    /** @return array<int, array<string, mixed>> */
    public function detect(array $transactions): array
    {
        return $this->eligible($transactions)
            ->groupBy(fn (array $t) => ($t['creditCardMetadata']['cardNumber'] ?? '-')
                .'|'.number_format((float) $t['amount'], 2, '.', ''))
            ->map(fn (Collection $group) => $this->summarize($group))
            ->filter()
            ->sortByDesc('amount')
            ->values()
            ->all();
    }

    /**
     * Só gastos: estornos, linhas de rotativo e parcelas ficam fora. Parcelas já
     * são projetadas pelo installmentNumber, contá-las aqui duplicaria.
     */
    private function eligible(array $transactions): Collection
    {
        return collect($transactions)->filter(function (array $transaction) {
            if ((float) $transaction['amount'] <= 0) {
                return false;
            }

            if (stripos($transaction['description'] ?? '', 'FATURA ANTERIOR') !== false) {
                return false;
            }

            return (int) ($transaction['creditCardMetadata']['totalInstallments'] ?? 0) < 2;
        });
    }

    /** Devolve a cobrança recorrente do grupo, ou null se não tiver o padrão. */
    private function summarize(Collection $group): ?array
    {
        $byMonth = $group
            ->sortBy(fn (array $t) => $t['date'])
            ->keyBy(fn (array $t) => Carbon::parse($t['date'])->format('Y-m'));

        if ($byMonth->count() < self::MIN_OCCURRENCES) {
            return null;
        }

        $days = $byMonth->map(fn (array $t) => (int) Carbon::parse($t['date'])->day);

        if ($days->max() - $days->min() > self::DAY_TOLERANCE) {
            return null;
        }

        $latest = $byMonth->last();
        $first = Carbon::parse($byMonth->first()['date']);
        $last = Carbon::parse($latest['date']);

        // Quantos dos meses do intervalo têm cobrança: 1.0 = todos, sem falhas.
        $span = $first->diffInMonths($last) + 1;

        return [
            'description' => $latest['description'],
            'card_number' => $latest['creditCardMetadata']['cardNumber'] ?? null,
            'amount' => round((float) $latest['amount'], 2),
            'day_of_month' => (int) $days->last(),
            'occurrences' => $byMonth->count(),
            'first_seen' => $first->toDateString(),
            'last_seen' => $last->toDateString(),
            'regularity_score' => round($byMonth->count() / max(1, $span), 2),
            'category' => $latest['category'] ?? null,
        ];
    }
}
