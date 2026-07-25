<?php

return [
    'client_id' => env('PLUGGY_CLIENT_ID'),
    'client_secret' => env('PLUGGY_CLIENT_SECRET'),
    'base_url' => env('PLUGGY_BASE_URL', 'https://api.pluggy.ai'),

    'items' => [
        'nubank' => env('PLUGGY_ITEM_ID_NUBANK'),
        'caixa' => env('PLUGGY_ITEM_ID_CAIXA'),
    ],
];
