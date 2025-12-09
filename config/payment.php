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
    | Default Payment Gateway
    |--------------------------------------------------------------------------
    */
    'default_gateway' => env('PAYMENT_DEFAULT_GATEWAY', 'mercadopago'),

    /*
    |--------------------------------------------------------------------------
    | Configurações do Gateway de Pagamento
    |--------------------------------------------------------------------------
    */

    'gateways' => [
        'mercadopago' => [
            'class' => \UendelSilveira\PaymentModuleManager\Gateways\MercadoPagoGateway::class,

            // Credenciais para autenticação direta do Mercado Pago
            'public_key' => env('MERCADOPAGO_PUBLIC_KEY', ''),
            'access_token' => env('MERCADOPAGO_ACCESS_TOKEN', ''),
            'webhook_secret' => env('MERCADOPAGO_WEBHOOK_SECRET', ''),

            // Credenciais para o fluxo OAuth 2.0 (Mercado Pago Connect)
            'client_id' => env('MERCADOPAGO_CLIENT_ID', ''),
            'client_secret' => env('MERCADOPAGO_CLIENT_SECRET', ''),

            // Configurações opcionais
            'sandbox' => env('APP_ENV') !== 'production',
            'timeout' => 30,
            'max_retries' => 3,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Monetary Limits
    |--------------------------------------------------------------------------
    */
    'monetary_limits' => [
        'mercadopago' => [
            'pix' => [
                'min' => env('MP_PIX_MIN_AMOUNT', 1),
                'max' => env('MP_PIX_MAX_AMOUNT', 1000000),
            ],
            'credit_card' => [
                'min' => env('MP_CREDIT_CARD_MIN_AMOUNT', 100),
                'max' => env('MP_CREDIT_CARD_MAX_AMOUNT', 5000000),
            ],
            'default' => [
                'min' => env('MP_DEFAULT_MIN_AMOUNT', 100),
                'max' => env('MP_DEFAULT_MAX_AMOUNT', 10000000),
            ],
        ],
        'global' => [
            'min' => env('PAYMENT_GLOBAL_MIN_AMOUNT', 100),
            'max' => env('PAYMENT_GLOBAL_MAX_AMOUNT', 10000000),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Refund Rules Configuration
    |--------------------------------------------------------------------------
    */
    'refund_rules' => [
        'mercadopago' => [
            'time_window_days' => 180,
            'supported_methods' => ['credit_card', 'debit_card', 'pix'],
            'requires_settlement' => false,
        ],
        'global' => [
            'time_window_days' => 90,
            'supported_methods' => ['credit_card', 'debit_card'],
            'requires_settlement' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry Configuration
    |--------------------------------------------------------------------------
    */
    'retry' => [
        'max_attempts' => env('PAYMENT_MAX_RETRY_ATTEMPTS', 3),
        'retry_interval_minutes' => env('PAYMENT_RETRY_INTERVAL_MINUTES', 5),
    ],
];
