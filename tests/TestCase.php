<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Coloca a sessão no estado pós-login. Para testes que precisam atravessar
     * o RequireAppPassword sem que o login seja o assunto deles.
     */
    protected function unlocked(): static
    {
        return $this->withSession(['unlocked' => true]);
    }
}
