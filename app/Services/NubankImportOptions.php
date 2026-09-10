<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * Parâmetros da importação do Nubank, iguais para as duas portas de entrada:
 * as flags do comando `import:pluggy-nubank` e o formulário do dashboard.
 *
 * `from` e `to` nulos significam "use o padrão", resolvido dentro do
 * NubankImportService — assim CLI e web caem sempre no mesmo período.
 */
class NubankImportOptions
{
    public function __construct(
        public readonly ?Carbon $from = null,
        public readonly ?Carbon $to = null,
        public readonly bool $dryRun = false,
        public readonly bool $replaceInvestments = false,
    ) {
    }

    /** Monta a partir de strings de data (flags do console ou campos do formulário). */
    public static function fromInput(
        ?string $from = null,
        ?string $to = null,
        bool $dryRun = false,
        bool $replaceInvestments = false,
    ): self {
        return new self(
            from: $from ? Carbon::parse($from)->startOfDay() : null,
            to: $to ? Carbon::parse($to)->endOfDay() : null,
            dryRun: $dryRun,
            replaceInvestments: $replaceInvestments,
        );
    }
}
