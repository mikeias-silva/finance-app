<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * O que a importação fez.
 *
 * Guarda duas leituras dos mesmos fatos, porque as duas portas de entrada
 * mostram o resultado de formas diferentes: `messages` é o log linha a linha
 * que o comando imprime no console; os arrays estruturados abaixo são o que o
 * dashboard renderiza como tabela.
 */
class NubankImportResult
{
    /** @var NubankImportMessage[] */
    public array $messages = [];

    /** Lançamentos gravados: ['type' => 'expense'|'income', 'date', 'amount', 'description']. */
    public array $entries = [];

    /** Gastos fixos e faturas encontrados no extrato, com pago vs previsto. */
    public array $fixedExpenses = [];

    /** Salário do ciclo, quando identificado: ['date', 'received', 'expected', 'difference']. */
    public ?array $salary = null;

    /** Caixinhas: ['name', 'database', 'api', 'matches']. */
    public array $investmentBoxes = [];

    /** Posições sem caixinha cadastrada: ['rate', 'balance']. */
    public array $unknownRates = [];

    public int $created = 0;
    public int $skipped = 0;
    public int $confirmed = 0;
    public int $investmentMovements = 0;
    public int $apiTransactionCount = 0;
    public int $activePositions = 0;
    public int $storedMovements = 0;
    public bool $replacedInvestments = false;

    public function __construct(
        public readonly Carbon $from,
        public readonly Carbon $to,
        public readonly bool $dryRun,
    ) {
    }

    public function add(NubankImportMessage $message): void
    {
        $this->messages[] = $message;
    }

    /** Uma frase para o flash da web e para a última linha do comando. */
    public function summary(): string
    {
        if ($this->dryRun) {
            return "Simulação concluída: {$this->created} lançamentos seriam gravados, "
                ."{$this->skipped} já existiam. Nada foi alterado.";
        }

        $summary = "Importação concluída: {$this->created} lançamentos gravados, "
            ."{$this->skipped} já existiam.";

        if ($this->replacedInvestments) {
            $summary .= ' Caixinhas regravadas.';
        }

        return $summary;
    }

    /** Só o que a view precisa, em tipos que sobrevivem à sessão. */
    public function toArray(): array
    {
        return [
            'from' => $this->from->toDateString(),
            'to' => $this->to->toDateString(),
            'dry_run' => $this->dryRun,
            'created' => $this->created,
            'skipped' => $this->skipped,
            'confirmed' => $this->confirmed,
            'investment_movements' => $this->investmentMovements,
            'api_transaction_count' => $this->apiTransactionCount,
            'active_positions' => $this->activePositions,
            'stored_movements' => $this->storedMovements,
            'replaced_investments' => $this->replacedInvestments,
            'entries' => $this->entries,
            'fixed_expenses' => $this->fixedExpenses,
            'salary' => $this->salary,
            'investment_boxes' => $this->investmentBoxes,
            'unknown_rates' => $this->unknownRates,
        ];
    }
}
