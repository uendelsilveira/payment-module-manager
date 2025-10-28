<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 28/10/2025 20:43:21
*/

return [
    /*
    |--------------------------------------------------------------------------
    | Configurações do Gateway de Pagamento
    |--------------------------------------------------------------------------
    */

    'gateways' => [
        'mercadopago' => [
            // Credenciais para autenticação direta (legado)
            'public_key' => env('MERCADOPAGO_PUBLIC_KEY', ''),
            'access_token' => env('MERCADOPAGO_ACCESS_TOKEN', ''),
            'webhook_secret' => env('MERCADOPAGO_WEBHOOK_SECRET', ''),

            // Credenciais para o fluxo OAuth 2.0 (Mercado Pago Connect)
            'client_id' => env('MERCADOPAGO_CLIENT_ID', ''),
            'client_secret' => env('MERCADOPAGO_CLIENT_SECRET', ''),
        ],
    ],
];
