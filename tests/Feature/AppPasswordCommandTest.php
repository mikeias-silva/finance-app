<?php

namespace Tests\Feature;

use App\Console\Commands\SetAppPassword;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\TestCase;

class AppPasswordCommandTest extends TestCase
{
    /**
     * O PendingCommand do Laravel entrega um mock como saída, então o texto
     * impresso não é legível por ele. O CommandTester roda o comando de verdade
     * e guarda o que foi escrito.
     */
    private function runCommand(array $answers): CommandTester
    {
        $command = $this->app->make(SetAppPassword::class);
        $command->setLaravel($this->app);
        $command->setApplication(new Application);

        $tester = new CommandTester($command);
        $tester->setInputs($answers);
        $tester->execute([], ['interactive' => true]);

        return $tester;
    }

    public function test_it_prints_a_hash_that_matches_the_typed_password(): void
    {
        $tester = $this->runCommand(['minha-senha', 'minha-senha']);

        $tester->assertCommandIsSuccessful();

        preg_match("/APP_PASSWORD_HASH='(.+)'/", $tester->getDisplay(), $matches);

        $this->assertNotEmpty($matches, 'O comando não imprimiu a linha do .env.');
        $this->assertTrue(Hash::check('minha-senha', $matches[1]));
    }

    public function test_it_refuses_when_the_confirmation_does_not_match(): void
    {
        $tester = $this->runCommand(['minha-senha', 'digitei-errado']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringNotContainsString('APP_PASSWORD_HASH', $tester->getDisplay());
    }

    public function test_it_refuses_an_empty_password(): void
    {
        $tester = $this->runCommand(['']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringNotContainsString('APP_PASSWORD_HASH', $tester->getDisplay());
    }
}
