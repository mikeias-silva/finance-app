<?php

namespace App\Services;

/**
 * Uma linha do log da importação, em texto puro.
 *
 * O service não pode falar direto com o console — ele roda também numa request
 * web. Então descreve a linha e deixa cada porta de entrada renderizar: o comando
 * escolhe o método do Symfony pelo nível, a view escolhe a cor.
 */
class NubankImportMessage
{
    public const INFO = 'info';
    public const LINE = 'line';
    public const WARN = 'warn';

    public function __construct(
        public readonly string $level,
        public readonly string $text,
    ) {
    }
}
