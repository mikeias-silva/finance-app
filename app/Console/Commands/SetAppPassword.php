<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class SetAppPassword extends Command
{
    protected $signature = 'app:password';

    protected $description = 'Gera o hash da senha de acesso para colar em APP_PASSWORD_HASH';

    public function handle(): int
    {
        // secret() não ecoa o que é digitado, então a senha não fica na tela
        // nem no histórico do shell.
        $password = (string) $this->secret('Digite a senha de acesso');

        if (trim($password) === '') {
            $this->error('Senha vazia. Nada foi gerado.');

            return self::FAILURE;
        }

        // Sem confirmação, um erro de digitação geraria o hash de uma senha que
        // você não conhece — e o app ficaria trancado.
        if ($password !== (string) $this->secret('Confirme a senha')) {
            $this->error('As senhas não conferem. Nada foi gerado.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->line('Cole esta linha no .env — as aspas simples são obrigatórias,');
        $this->line('senão o "$2y$" é lido como variável e o hash chega mutilado:');
        $this->newLine();
        $this->line("APP_PASSWORD_HASH='".Hash::make($password)."'");
        $this->newLine();

        return self::SUCCESS;
    }
}
