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
        'require_signature' => env('PAYMENT_WEBHOOK_REQUIRE_SIGNATURE', false),

        // Idade máxima permitida para webhooks (em segundos) - proteção contra replay attacks
        'max_age_seconds' => env('PAYMENT_WEBHOOK_MAX_AGE', 300),

        // Enable asynchronous webhook processing via queues
        'async_processing' => env('PAYMENT_WEBHOOK_ASYNC', true),

        // Queue name for webhook processing
        'queue_name' => env('PAYMENT_WEBHOOK_QUEUE', 'webhooks'),
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

    /*
    |--------------------------------------------------------------------------
    | Retry Strategy
    |--------------------------------------------------------------------------
    |
    | Configurações para tentativas automáticas de reprocessamento de pagamentos.
    |
    */

    'retry' => [
        // Habilitar sistema de retry automático
        'enabled' => env('PAYMENT_RETRY_ENABLED', true),

        // Número máximo de tentativas
        'max_attempts' => env('PAYMENT_RETRY_MAX_ATTEMPTS', 3),

        // Estratégia de backoff: 'fixed', 'exponential'
        'backoff_strategy' => env('PAYMENT_RETRY_BACKOFF_STRATEGY', 'exponential'),

        // Delay inicial entre tentativas (em segundos)
        'initial_delay' => env('PAYMENT_RETRY_INITIAL_DELAY', 60),

        // Multiplicador para backoff exponencial (se backoff_strategy = 'exponential')
        // Delay = initial_delay * (multiplier ^ attempt)
        'backoff_multiplier' => env('PAYMENT_RETRY_BACKOFF_MULTIPLIER', 2),

        // Delay máximo entre tentativas (em segundos)
        'max_delay' => env('PAYMENT_RETRY_MAX_DELAY', 3600),

        // Condições para retry baseadas no status da transação
        'retryable_statuses' => [
            'pending',
            'failed',
            'error',
        ],

        // Condições para retry baseadas em códigos de erro do gateway
        'retryable_error_codes' => [
            'timeout',
            'connection_error',
            'service_unavailable',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Monetary Limits
    |--------------------------------------------------------------------------
    |
    | Define minimum and maximum transaction amounts per payment method and gateway.
    | Amounts are in the smallest currency unit (e.g., cents for BRL).
    |
    */

    'monetary_limits' => [
        'mercadopago' => [
            'pix' => [
                'min' => env('PAYMENT_PIX_MIN_AMOUNT', 1), // R$ 0.01
                'max' => env('PAYMENT_PIX_MAX_AMOUNT', 1000000), // R$ 10,000.00
            ],
            'credit_card' => [
                'min' => env('PAYMENT_CREDIT_CARD_MIN_AMOUNT', 500), // R$ 5.00
                'max' => env('PAYMENT_CREDIT_CARD_MAX_AMOUNT', 5000000), // R$ 50,000.00
            ],
            'debit_card' => [
                'min' => env('PAYMENT_DEBIT_CARD_MIN_AMOUNT', 500), // R$ 5.00
                'max' => env('PAYMENT_DEBIT_CARD_MAX_AMOUNT', 1000000), // R$ 10,000.00
            ],
            'boleto' => [
                'min' => env('PAYMENT_BOLETO_MIN_AMOUNT', 500), // R$ 5.00
                'max' => env('PAYMENT_BOLETO_MAX_AMOUNT', 10000000), // R$ 100,000.00
            ],
            'default' => [
                'min' => env('PAYMENT_DEFAULT_MIN_AMOUNT', 100), // R$ 1.00
                'max' => env('PAYMENT_DEFAULT_MAX_AMOUNT', 10000000), // R$ 100,000.00
            ],
        ],

        // Global fallback limits
        'global' => [
            'min' => env('PAYMENT_GLOBAL_MIN_AMOUNT', 100), // R$ 1.00
            'max' => env('PAYMENT_GLOBAL_MAX_AMOUNT', 10000000), // R$ 100,000.00
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-Currency Support
    |--------------------------------------------------------------------------
    |
    | Configure supported currencies and conversion settings.
    |
    */

    'currencies' => [
        // Default currency
        'default' => env('PAYMENT_DEFAULT_CURRENCY', 'BRL'),

        // Supported currencies
        'supported' => [
            'BRL' => [
                'name' => 'Brazilian Real',
                'symbol' => 'R$',
                'decimal_places' => 2,
            ],
            'USD' => [
                'name' => 'US Dollar',
                'symbol' => '$',
                'decimal_places' => 2,
            ],
            'EUR' => [
                'name' => 'Euro',
                'symbol' => '€',
                'decimal_places' => 2,
            ],
            'ARS' => [
                'name' => 'Argentine Peso',
                'symbol' => '$',
                'decimal_places' => 2,
            ],
        ],

        // Currency conversion (if needed for multi-currency transactions)
        'conversion' => [
            // Enable automatic conversion
            'enabled' => env('PAYMENT_CURRENCY_CONVERSION', false),

            // API provider for exchange rates (e.g., 'exchangerate-api', 'fixer', 'custom')
            'provider' => env('PAYMENT_CURRENCY_PROVIDER', 'exchangerate-api'),

            // API key for currency conversion service
            'api_key' => env('PAYMENT_CURRENCY_API_KEY', ''),

            // Cache exchange rates (in minutes)
            'cache_ttl' => env('PAYMENT_CURRENCY_CACHE_TTL', 60),
        ],
    ],
];
