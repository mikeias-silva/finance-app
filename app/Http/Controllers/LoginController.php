<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function show(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'string']]);

        $hash = (string) config('simple_auth.hash');

        // Fail-closed: sem hash configurado ninguém entra. A mensagem é
        // específica de propósito — sem ela, um deploy que esqueceu a variável
        // responde "senha incorreta" para sempre, sem pista do motivo.
        if ($hash === '') {
            return back()->withErrors([
                'password' => 'APP_PASSWORD_HASH não está configurado no .env.',
            ]);
        }

        if (! Hash::check($request->string('password')->value(), $hash)) {
            return back()->withErrors(['password' => 'Senha incorreta.']);
        }

        // Troca o ID da sessão ao entrar: fecha session fixation, em que o
        // atacante planta um ID conhecido antes do login e o herda depois.
        $request->session()->regenerate();
        $request->session()->put('unlocked', true);

        return redirect()->intended('/');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
