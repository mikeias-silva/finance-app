<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'senha-de-teste';

    protected function setUp(): void
    {
        parent::setUp();

        config(['simple_auth.hash' => Hash::make(self::PASSWORD)]);
    }

    public function test_login_screen_renders(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('name="password"', false);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/fixed-expenses')->assertRedirect('/login');
    }

    public function test_correct_password_unlocks_the_app(): void
    {
        $this->post('/login', ['password' => self::PASSWORD])
            ->assertRedirect('/');

        $this->get('/fixed-expenses')->assertOk();
    }

    public function test_wrong_password_is_rejected(): void
    {
        $this->from('/login')
            ->post('/login', ['password' => 'senha-errada'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('password');

        $this->get('/fixed-expenses')->assertRedirect('/login');
    }

    public function test_password_is_required(): void
    {
        $this->from('/login')
            ->post('/login', [])
            ->assertSessionHasErrors('password');
    }

    public function test_login_attempts_are_rate_limited(): void
    {
        // O limite é 5 por minuto; a sexta tentativa já deve ser barrada.
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['password' => 'chute-'.$i]);
        }

        $this->post('/login', ['password' => 'chute-6'])
            ->assertStatus(429);
    }

    public function test_logout_locks_the_app_again(): void
    {
        $this->post('/login', ['password' => self::PASSWORD]);

        $this->post('/logout')->assertRedirect('/login');

        $this->get('/fixed-expenses')->assertRedirect('/login');
    }

    public function test_login_returns_to_the_page_originally_requested(): void
    {
        $this->get('/fixed-expenses');

        $this->post('/login', ['password' => self::PASSWORD])
            ->assertRedirect('/fixed-expenses');
    }

    public function test_json_routes_answer_401_instead_of_redirecting_to_html(): void
    {
        $this->get('/accounts')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Não autenticado.']);
    }

    public function test_app_stays_locked_when_the_hash_is_not_configured(): void
    {
        config(['simple_auth.hash' => null]);

        $this->from('/login')
            ->post('/login', ['password' => self::PASSWORD])
            ->assertSessionHasErrors([
                'password' => 'APP_PASSWORD_HASH não está configurado no .env.',
            ]);

        $this->get('/fixed-expenses')->assertRedirect('/login');
    }
}
