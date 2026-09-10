<?php

namespace App\Console\Commands;

use App\Models\FixedExpense;
use App\Models\InvestmentBox;
use App\Models\InvestmentTransaction;
use App\Models\Salary;
use App\Models\VariableExpense;
use App\Models\VariableIncome;
use App\Services\InvestmentService;
use App\Services\StatementClassifier;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ImportVariableExpenses extends Command
{
    protected $signature = 'import:variable-expenses {file : Caminho do arquivo CSV (relativo a storage/app/private)}';

    protected $description = 'Importa extrato CSV: débitos vão para variable_expenses, créditos para variable_incomes';

    public function handle(StatementClassifier $classifier): int
    {
        $file = storage_path('app/private/' . $this->argument('file'));

        if (! file_exists($file)) {
            $this->error("Arquivo não encontrado: {$file}");
            return self::FAILURE;
        }

        $handle = fopen($file, 'r');
        fgetcsv($handle); // pula o cabeçalho

        $expenses    = 0;
        $incomes     = 0;
        $confirmed   = 0;
        $adjustments = 0;
        $investments = 0;

        // Carrega caixinhas ordenadas: maior rendimento primeiro
        $boxes = InvestmentBox::with('transactions')->where('is_active', true)
            ->orderByDesc('rate_percentage')
            ->get();
        $investmentService = app(InvestmentService::class);

        while (($row = fgetcsv($handle)) !== false) {
            [$data, $valor, $identificador, $descricao] = $row;

            $amount = (float) str_replace(',', '.', $valor);
            $date   = Carbon::createFromFormat('d/m/Y', $data)->toDateString();

            if (stripos($descricao, 'RDB') !== false) {
                // Ignora RDB anterior a julho/2026
                if ($date < '2026-07-01') {
                    continue;
                }
                $isDeposit = $amount < 0; // débito da conta = aplicação
                $type      = $isDeposit ? 'deposit' : 'withdrawal';
                $total     = abs($amount);

                if ($isDeposit) {
                    // Distribui: caixinha de maior rendimento (até max_amount), resto na ilimitada
                    $remaining = $total;
                    foreach ($boxes as $box) {
                        if ($remaining <= 0) break;

                        if ($box->max_amount !== null) {
                            $currentBalance = $investmentService->currentBalance($box);
                            $available      = max(0, $box->max_amount - $currentBalance);
                            $toApply        = min($remaining, $available);
                        } else {
                            $toApply = $remaining;
                        }

                        if ($toApply <= 0) continue;

                        $suffix = $box->max_amount ? "-box{$box->id}-part" : "-box{$box->id}";
                        InvestmentTransaction::firstOrCreate(
                            ['identifier' => $identificador . $suffix],
                            [
                                'investment_box_id' => $box->id,
                                'type'              => 'deposit',
                                'amount'            => round($toApply, 2),
                                'transacted_at'     => $date,
                                'notes'             => $descricao,
                            ]
                        );
                        // Atualiza o saldo em memória para a próxima iteração
                        $box->transactions->push((object)['type' => 'deposit', 'amount' => $toApply]);
                        $remaining -= $toApply;
                        $investments++;
                        $this->line("  💰 Aplicação RDB R$ " . number_format($toApply, 2, ',', '.') . " → {$box->name}");
                    }
                } else {
                    // Resgate: retira primeiro da caixinha de MENOR rendimento (100% CDI)
                    $remaining   = $total;
                    $boxesAsc    = $boxes->sortBy('rate_percentage')->values();
                    foreach ($boxesAsc as $box) {
                        if ($remaining <= 0) break;

                        $currentBalance = $investmentService->currentBalance($box);
                        $toWithdraw     = min($remaining, $currentBalance);

                        if ($toWithdraw <= 0) continue;

                        InvestmentTransaction::firstOrCreate(
                            ['identifier' => $identificador . "-box{$box->id}"],
                            [
                                'investment_box_id' => $box->id,
                                'type'              => 'withdrawal',
                                'amount'            => round($toWithdraw, 2),
                                'transacted_at'     => $date,
                                'notes'             => $descricao,
                            ]
                        );
                        $remaining -= $toWithdraw;
                        $investments++;
                        $this->line("  🏦 Resgate RDB R$ " . number_format($toWithdraw, 2, ',', '.') . " ← {$box->name}");
                    }
                }

                continue;
            }

            // Verifica se é recebimento de salário (apenas confirma, não cria receita variável)
            if ($amount > 0 && $classifier->isSalary($descricao)) {
                $salary   = Salary::where('effective_from', '<=', $date)->orderByDesc('effective_from')->first();
                $actual   = $amount;
                $expected = $salary?->amount ?? 0;
                $diff     = round($actual - $expected, 2);

                if ($diff > 0) {
                    $this->line("  ↑ <comment>Salário</comment> — recebido R$ " . number_format($actual, 2, ',', '.') . " / previsto R$ " . number_format($expected, 2, ',', '.') . " → excedente R$ " . number_format($diff, 2, ',', '.'));
                } elseif ($diff < 0) {
                    $this->line("  ↓ <comment>Salário</comment> — recebido R$ " . number_format($actual, 2, ',', '.') . " / previsto R$ " . number_format($expected, 2, ',', '.') . " → faltou R$ " . number_format(abs($diff), 2, ',', '.'));
                } else {
                    $this->line("  ✓ <comment>Salário</comment> confirmado: R$ " . number_format($actual, 2, ',', '.') . " em {$date}");
                }

                $confirmed++;
                continue;
            }

            // Verifica se é um gasto fixo conhecido
            $fixedMatch = $classifier->matchFixedExpense($descricao);
            if ($fixedMatch !== null) {
                ['label' => $label, 'db_description' => $dbDescription] = $fixedMatch;

                if ($dbDescription !== null) {
                    $fixedExpense = FixedExpense::where('description', $dbDescription)->where('is_active', true)->first();

                    if ($fixedExpense) {
                        $actual   = abs($amount);
                        $expected = (float) $fixedExpense->amount;
                        $diff     = round($actual - $expected, 2);

                        if ($diff > 0) {
                            // Pagou mais que o previsto → diferença vai para despesa variável
                            VariableExpense::firstOrCreate(
                                ['identifier' => $identificador],
                                ['description' => "Ajuste {$label}", 'amount' => $diff, 'paid_at' => $date]
                            );
                            $this->line("  ↑ <comment>{$label}</comment> — pago R$ " . number_format($actual, 2, ',', '.') . " / previsto R$ " . number_format($expected, 2, ',', '.') . " → +R$ " . number_format($diff, 2, ',', '.') . " em despesa variável");
                            $adjustments++;
                        } elseif ($diff < 0) {
                            // Pagou menos que o previsto → diferença vai para receita variável (corrige o saldo)
                            VariableIncome::firstOrCreate(
                                ['identifier' => $identificador],
                                ['description' => "Ajuste {$label}", 'amount' => abs($diff), 'received_at' => $date]
                            );
                            $this->line("  ↓ <comment>{$label}</comment> — pago R$ " . number_format($actual, 2, ',', '.') . " / previsto R$ " . number_format($expected, 2, ',', '.') . " → economia R$ " . number_format(abs($diff), 2, ',', '.') . " em receita variável");
                            $adjustments++;
                        } else {
                            $this->line("  ✓ <comment>{$label}</comment> confirmado: R$ " . number_format($actual, 2, ',', '.') . " em {$date}");
                        }
                    }
                } else {
                    $this->line("  ✓ <comment>{$label}</comment> confirmado: R$ " . number_format(abs($amount), 2, ',', '.') . " em {$date}");
                }

                $confirmed++;
                continue;
            }

            if ($amount < 0) {
                VariableExpense::firstOrCreate(
                    ['identifier' => $identificador],
                    [
                        'description' => $descricao,
                        'amount'      => abs($amount),
                        'paid_at'     => $date,
                    ]
                );
                $expenses++;
            } else {
                VariableIncome::firstOrCreate(
                    ['identifier' => $identificador],
                    [
                        'description' => $descricao,
                        'amount'      => $amount,
                        'received_at' => $date,
                    ]
                );
                $incomes++;
            }
        }

        fclose($handle);

        $this->newLine();
        $this->info("✅ {$expenses} despesas importadas → variable_expenses");
        $this->info("✅ {$incomes} recebimentos importados → variable_incomes");
        $this->info("✅ {$investments} transações RDB → investment_transactions");
        $this->info("✔  {$confirmed} gastos fixos confirmados ({$adjustments} com ajuste de diferença)");

        return self::SUCCESS;
    }

}
