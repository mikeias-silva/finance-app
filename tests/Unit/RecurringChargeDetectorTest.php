<?php

namespace Tests\Unit;

use App\Services\RecurringChargeDetector;
use PHPUnit\Framework\TestCase;

/**
 * A detecção substitui o endpoint de recurring-payments do Pluggy, que devolve
 * vazio nesta conta. O agrupamento é por cartão + valor + dia do mês porque a
 * descrição da mesma assinatura muda a cada fatura.
 */
class RecurringChargeDetectorTest extends TestCase
{
    private RecurringChargeDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->detector = new RecurringChargeDetector;
    }

    public function test_three_monthly_charges_with_a_stable_amount_are_a_subscription(): void
    {
        $found = $this->detector->detect([
            $this->charge('2026-01-10', 49.90, 'Wellhub Mikeias Silva Gom'),
            $this->charge('2026-02-10', 49.90, 'Wellhub'),
            $this->charge('2026-03-10', 49.90, 'Wellhub Gympass BR Wellhu'),
        ]);

        $this->assertCount(1, $found);
        // A descrição exibida é a do lançamento mais recente.
        $this->assertSame('Wellhub Gympass BR Wellhu', $found[0]['description']);
        $this->assertSame(49.90, $found[0]['amount']);
        $this->assertSame(10, $found[0]['day_of_month']);
        $this->assertSame(3, $found[0]['occurrences']);
        $this->assertSame('2026-01-10', $found[0]['first_seen']);
        $this->assertSame('2026-03-10', $found[0]['last_seen']);
        // Três cobranças em três meses seguidos: nenhuma falha no intervalo.
        $this->assertSame(1.0, $found[0]['regularity_score']);
    }

    public function test_two_occurrences_are_not_enough(): void
    {
        $found = $this->detector->detect([
            $this->charge('2026-01-10', 49.90, 'Wellhub'),
            $this->charge('2026-02-10', 49.90, 'Wellhub'),
        ]);

        $this->assertSame([], $found);
    }

    public function test_a_day_of_month_that_drifts_too_far_is_not_a_subscription(): void
    {
        $found = $this->detector->detect([
            $this->charge('2026-01-05', 49.90, 'Compra'),
            $this->charge('2026-02-08', 49.90, 'Compra'),
            $this->charge('2026-03-12', 49.90, 'Compra'),
        ]);

        // 12 − 5 = 7 dias de variação, acima da tolerância de 5.
        $this->assertSame([], $found);
    }

    public function test_two_charges_in_the_same_month_count_as_one(): void
    {
        $found = $this->detector->detect([
            $this->charge('2026-01-10', 49.90, 'Wellhub'),
            $this->charge('2026-01-11', 49.90, 'Wellhub'),
            $this->charge('2026-02-10', 49.90, 'Wellhub'),
        ]);

        $this->assertSame([], $found);
    }

    public function test_refunds_carry_forward_lines_and_installments_are_ignored(): void
    {
        $found = $this->detector->detect(array_merge(
            $this->threeMonthsOf(-49.90, 'Estorno assinatura'),
            $this->threeMonthsOf(120.00, 'SALDO FATURA ANTERIOR'),
            $this->threeMonthsOf(300.00, 'Geladeira', installments: 10),
        ));

        $this->assertSame([], $found);
    }

    public function test_the_same_amount_on_different_cards_is_not_the_same_subscription(): void
    {
        $found = $this->detector->detect([
            $this->charge('2026-01-10', 49.90, 'Wellhub', card: '1234'),
            $this->charge('2026-02-10', 49.90, 'Wellhub', card: '1234'),
            $this->charge('2026-03-10', 49.90, 'Wellhub', card: '9999'),
        ]);

        $this->assertSame([], $found);
    }

    public function test_a_gap_in_the_middle_lowers_the_regularity_score(): void
    {
        $found = $this->detector->detect([
            $this->charge('2026-01-10', 49.90, 'Wellhub'),
            $this->charge('2026-02-10', 49.90, 'Wellhub'),
            $this->charge('2026-04-10', 49.90, 'Wellhub'),
        ]);

        // Três cobranças num intervalo de quatro meses: março faltou.
        $this->assertCount(1, $found);
        $this->assertSame(0.75, $found[0]['regularity_score']);
    }

    public function test_the_biggest_subscription_comes_first(): void
    {
        $found = $this->detector->detect(array_merge(
            $this->threeMonthsOf(49.90, 'Wellhub'),
            $this->threeMonthsOf(129.90, 'Plano de saúde'),
        ));

        $this->assertSame([129.90, 49.90], array_column($found, 'amount'));
    }

    /** @return array<int, array<string, mixed>> */
    private function threeMonthsOf(float $amount, string $description, int $installments = 0): array
    {
        return [
            $this->charge('2026-01-10', $amount, $description, installments: $installments),
            $this->charge('2026-02-10', $amount, $description, installments: $installments),
            $this->charge('2026-03-10', $amount, $description, installments: $installments),
        ];
    }

    /** Lançamento de cartão no formato que a API do Pluggy devolve. */
    private function charge(
        string $date,
        float $amount,
        string $description,
        string $card = '1234',
        int $installments = 0,
    ): array {
        return [
            'date' => $date.'T00:00:00.000Z',
            'amount' => $amount,
            'description' => $description,
            'category' => 'Serviços',
            'creditCardMetadata' => [
                'cardNumber' => $card,
                'totalInstallments' => $installments,
            ],
        ];
    }
}
