<?php

namespace Database\Seeders;

use App\Models\RecurringIncome;
use Illuminate\Database\Seeder;

/**
 * Registros de receita recorrente que já existem na vida real. Fica em seeder e
 * não em migration de dados para não entrar no banco de testes, onde cada teste
 * monta o próprio cenário.
 *
 * Rode uma vez por ambiente: php artisan db:seed --class=RecurringIncomeSeeder
 */
class RecurringIncomeSeeder extends Seeder
{
    public function run(): void
    {
        RecurringIncome::firstOrCreate(
            ['description' => 'Gratificação'],
            [
                // 30/09/2026 é o último dia útil de setembro: o pagamento que
                // abre o ciclo de outubro. É nele que a gratificação estreia.
                'amount' => 1342.05,
                'is_active' => true,
                'effective_from' => '2026-09-30',
                'valid_until' => null,
            ],
        );
    }
}
