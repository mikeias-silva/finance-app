<?php

namespace Tests\Feature;

use App\Models\BalanceSnapshot;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * A leitura de saldo diz se o salário já caiu, mas responde pelo ciclo em que foi
 * feita. Ciclos de referência: setembro/2026 (31/08 a 29/09) e outubro/2026
 * (30/09 a 29/10), com hoje em 09/10.
 */
class BalanceSnapshotTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-09');
    }

    public function test_a_reading_from_the_previous_cycle_has_not_seen_this_cycles_salary(): void
    {
        // Em 09/09 o salário de setembro já tinha caído; o de outubro só cai em 30/09.
        $reading = $this->readAt('2026-09-09 21:35:00', credited: true);

        $this->assertFalse($reading->salaryCreditedInCurrentCycle());
    }

    public function test_a_reading_from_the_previous_cycle_without_an_answer_has_not_seen_it_either(): void
    {
        // Sem resposta, o calendário diria que o salário de 30/09 já caiu — mas ele
        // não tem como estar num saldo lido antes disso.
        $reading = $this->readAt('2026-09-09 21:35:00', credited: null);

        $this->assertFalse($reading->salaryCreditedInCurrentCycle());
    }

    public function test_a_reading_from_this_cycle_keeps_its_answer(): void
    {
        $this->assertTrue($this->readAt('2026-10-01 23:02:00', credited: true)->salaryCreditedInCurrentCycle());
        $this->assertFalse($this->readAt('2026-10-01 23:02:00', credited: false)->salaryCreditedInCurrentCycle());
        // null continua deixando a decisão para o calendário.
        $this->assertNull($this->readAt('2026-10-01 23:02:00', credited: null)->salaryCreditedInCurrentCycle());
    }

    public function test_a_reading_on_salary_day_already_answers_for_the_new_cycle(): void
    {
        // 30/09 é o primeiro dia do ciclo de outubro: o que essa leitura viu vale para ele.
        $reading = $this->readAt('2026-09-30 08:00:00', credited: true);

        $this->assertTrue($reading->salaryCreditedInCurrentCycle());
    }

    private function readAt(string $syncedAt, ?bool $credited): BalanceSnapshot
    {
        return new BalanceSnapshot([
            'checking' => 0.0,
            'boxes' => 0.0,
            'total' => 0.0,
            'salary_credited' => $credited,
            'synced_at' => $syncedAt,
        ]);
    }
}
