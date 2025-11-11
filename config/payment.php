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
    |
    | This option controls the default payment gateway that will be used
    | when no specific gateway is requested.
    |
    | Supported: "mercadopago", "stripe", "paypal"
    |
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
        ],

        // Stripe configuration
        'stripe' => [
            'class' => \UendelSilveira\PaymentModuleManager\Gateways\StripeGateway::class,
            'api_key' => env('STRIPE_API_KEY'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        ],

        // PayPal configuration
        'paypal' => [
            'class' => \UendelSilveira\PaymentModuleManager\Gateways\PayPalGateway::class,
            'client_id' => env('PAYPAL_CLIENT_ID'),
            'client_secret' => env('PAYPAL_CLIENT_SECRET'),
            'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
            'sandbox' => env('PAYPAL_SANDBOX', true),
        ],

        'monetary_limits' => [
            // Exemplo de limites para um gateway específico
            'mercadopago' => [
                'pix' => [
                    'min' => env('MP_PIX_MIN_AMOUNT', 1), // R$ 0.01
                    'max' => env('MP_PIX_MAX_AMOUNT', 1000000), // R$ 10,000.00
                ],
                'credit_card' => [
                    'min' => env('MP_CREDIT_CARD_MIN_AMOUNT', 100), // R$ 1.00
                    'max' => env('MP_CREDIT_CARD_MAX_AMOUNT', 5000000), // R$ 50,000.00
                ],
                'debit_card' => [
                    'min' => env('MP_DEBIT_CARD_MIN_AMOUNT', 100), // R$ 1.00
                    'max' => env('MP_DEBIT_CARD_MAX_AMOUNT', 1000000), // R$ 10,000.00
                ],
                'boleto' => [
                    'min' => env('MP_BOLETO_MIN_AMOUNT', 100), // R$ 1.00
                    'max' => env('MP_BOLETO_MAX_AMOUNT', 10000000), // R$ 100,000.00
                ],
                'default' => [
                    'min' => env('MP_DEFAULT_MIN_AMOUNT', 100), // R$ 1.00
                    'max' => env('MP_DEFAULT_MAX_AMOUNT', 10000000), // R$ 100,000.00
                ],
            ],

            // Stripe
            'stripe' => [
                'credit_card' => [
                    'min' => env('STRIPE_CREDIT_CARD_MIN_AMOUNT', 100), // R$ 1.00
                    'max' => env('STRIPE_CREDIT_CARD_MAX_AMOUNT', 5000000), // R$ 50,000.00
                ],
                'default' => [
                    'min' => env('STRIPE_DEFAULT_MIN_AMOUNT', 100), // R$ 1.00
                    'max' => env('STRIPE_DEFAULT_MAX_AMOUNT', 10000000), // R$ 100,000.00
                ],
            ],

            // PayPal
            'paypal' => [
                'credit_card' => [
                    'min' => env('PAYPAL_CREDIT_CARD_MIN_AMOUNT', 100), // R$ 1.00
                    'max' => env('PAYPAL_CREDIT_CARD_MAX_AMOUNT', 5000000), // R$ 50,000.00
                ],
                'default' => [
                    'min' => env('PAYPAL_DEFAULT_MIN_AMOUNT', 100), // R$ 1.00
                    'max' => env('PAYPAL_DEFAULT_MAX_AMOUNT', 10000000), // R$ 100,000.00
                ],
            ],

            // Global fallback limits
            'global' => [
                'min' => env('PAYMENT_GLOBAL_MIN_AMOUNT', 100), // R$ 1.00
                'max' => env('PAYMENT_GLOBAL_MAX_AMOUNT', 10000000), // R$ 100,000.00
            ],
        ],
    ],
];
