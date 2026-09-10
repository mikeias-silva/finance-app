<?php

namespace App\Services;

use RuntimeException;

/**
 * Falha de pré-condição da importação (item do Pluggy não configurado, conta
 * não encontrada). A mensagem é escrita para ser mostrada ao usuário direto,
 * tanto no `$this->error()` do comando quanto no flash do dashboard.
 */
class NubankImportException extends RuntimeException
{
}
