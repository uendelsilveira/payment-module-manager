<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 04/11/2025 16:09:37
*/

return [
    /*
    |--------------------------------------------------------------------------
    | Payment Module Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configuração de logging estruturado para o módulo de pagamentos.
    | Usa Monolog para processadores e formatadores.
    |
    */

    'default' => env('PAYMENT_LOG_CHANNEL', 'payment'),

    'channels' => [
        'payment' => [
            'driver' => 'daily',
            'path' => storage_path('logs/payment.log'),
            'level' => env('PAYMENT_LOG_LEVEL', 'info'),
            'days' => 14,
            'replace_placeholders' => true,
        ],

        'webhook' => [
            'driver' => 'daily',
            'path' => storage_path('logs/payment-webhook.log'),
            'level' => env('PAYMENT_WEBHOOK_LOG_LEVEL', 'info'),
            'days' => 14,
            'replace_placeholders' => true,
        ],

        'gateway' => [
            'driver' => 'daily',
            'path' => storage_path('logs/payment-gateway.log'),
            'level' => env('PAYMENT_GATEWAY_LOG_LEVEL', 'info'),
            'days' => 14,
            'replace_placeholders' => true,
        ],

        'transaction' => [
            'driver' => 'daily',
            'path' => storage_path('logs/payment-transaction.log'),
            'level' => env('PAYMENT_TRANSACTION_LOG_LEVEL', 'info'),
            'days' => 30,
            'replace_placeholders' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Levels
    |--------------------------------------------------------------------------
    |
    | emergency: Sistema inutilizável
    | alert: Ação deve ser tomada imediatamente
    | critical: Condições críticas
    | error: Erros que não param a aplicação
    | warning: Avisos (deprecated, poor usage, etc)
    | notice: Eventos normais mas significativos
    | info: Eventos informativos
    | debug: Informações detalhadas de debug
    |
    */

    'sensitive_fields' => [
        'token',
        'access_token',
        'password',
        'card_number',
        'cvv',
        'security_code',
        'webhook_secret',
    ],
];
