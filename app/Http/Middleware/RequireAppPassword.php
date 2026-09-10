<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tranca o app inteiro atrás da senha única definida em APP_PASSWORD_HASH.
 *
 * Fica pendurado no grupo `web` inteiro em vez de rotas escolhidas a dedo:
 * assim toda rota nova nasce protegida, sem depender de alguém lembrar.
 */
class RequireAppPassword
{
    public function handle(Request $request, Closure $next): Response
    {
        // Sem esta exceção a própria tela de login se redirecionaria em loop.
        if ($request->is('login')) {
            return $next($request);
        }

        if ($request->session()->get('unlocked') === true) {
            return $next($request);
        }

        // As rotas /accounts/* respondem JSON (AccountController). Devolver a
        // elas um redirect para HTML entregaria uma tela de login onde o front
        // espera dados.
        if ($request->expectsJson() || $request->is('accounts*')) {
            abort(Response::HTTP_UNAUTHORIZED, 'Não autenticado.');
        }

        return redirect()->guest(route('login'));
    }
}
