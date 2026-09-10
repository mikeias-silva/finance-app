<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Senha única do app
    |--------------------------------------------------------------------------
    |
    | Hash bcrypt da senha de acesso. Gere com `php artisan app:password` e cole
    | o resultado no .env — sempre entre aspas simples, senão o phpdotenv lê o
    | "$2y$" como interpolação de variável e o hash chega mutilado.
    |
    | O valor mora aqui, e não num env() dentro do controller, porque o deploy
    | roda `php artisan config:cache` (DEPLOY.md) — com o config cacheado, env()
    | devolve null fora de config/, o que derrubaria o login só em produção.
    |
    */

    'hash' => env('APP_PASSWORD_HASH'),

];
