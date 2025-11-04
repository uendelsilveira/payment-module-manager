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

    /*
    |--------------------------------------------------------------------------
    | Configurações de Autenticação
    |--------------------------------------------------------------------------
    |
    | Define como as requisições de pagamento serão autenticadas.
    | Estratégias disponíveis: 'none', 'api_token', 'laravel_auth', 'custom'
    |
    */

    'auth' => [
        // Estratégia de autenticação: none, api_token, laravel_auth, custom
        'strategy' => env('PAYMENT_AUTH_STRATEGY', 'none'),

        // Token de API fixo (usado quando strategy = 'api_token')
        'api_token' => env('PAYMENT_API_TOKEN', ''),

        // Callback customizado (usado quando strategy = 'custom')
        // Deve retornar true se autenticado, false caso contrário
        'custom_callback' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Configurações de Autorização
    |--------------------------------------------------------------------------
    |
    | Define como as permissões serão verificadas.
    | Estratégias disponíveis: 'none', 'callback', 'laravel_gate'
    |
    */

    'authorization' => [
        // Estratégia de autorização: none, callback, laravel_gate
        'strategy' => env('PAYMENT_AUTHORIZATION_STRATEGY', 'none'),

        // Callback customizado (usado quando strategy = 'callback')
        // Deve retornar true se autorizado, false caso contrário
        // Recebe: ($user, $permission, $request)
        'callback' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Configurações de Webhook
    |--------------------------------------------------------------------------
    |
    | Configurações de segurança para webhooks.
    |
    */

    'webhook' => [
        // Exigir validação de assinatura (sempre true em produção)
        'require_signature' => env('PAYMENT_WEBHOOK_REQUIRE_SIGNATURE', true),

        // Idade máxima permitida para webhooks (em segundos) - proteção contra replay attacks
        'max_age_seconds' => env('PAYMENT_WEBHOOK_MAX_AGE', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configurações de limitação de taxa para proteger contra abuso.
    |
    */

    'rate_limiting' => [
        // Habilitar rate limiting
        'enabled' => env('PAYMENT_RATE_LIMITING_ENABLED', true),

        // Máximo de requisições por minuto para processamento de pagamentos
        'payment_process' => env('PAYMENT_RATE_LIMIT_PROCESS', 10),

        // Máximo de requisições por minuto para consultas
        'payment_query' => env('PAYMENT_RATE_LIMIT_QUERY', 60),

        // Máximo de requisições por minuto para webhooks
        'webhook' => env('PAYMENT_RATE_LIMIT_WEBHOOK', 100),

        // Máximo de requisições por minuto para configurações
        'settings' => env('PAYMENT_RATE_LIMIT_SETTINGS', 20),
    ],
];
