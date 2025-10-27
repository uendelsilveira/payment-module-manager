<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Configurações dos Gateways de Pagamento
    |--------------------------------------------------------------------------
    |
    | Aqui você pode definir as credenciais para cada gateway de pagamento
    | que seu aplicativo suporta. Preencha os valores de acordo com as
    | chaves fornecidas pelo provedor de pagamento.
    |
    */

    'gateways' => [
        'mercadopago' => [
            'public_key' => env('MERCADOPAGO_PUBLIC_KEY', ''),
            'access_token' => env('MERCADOPAGO_ACCESS_TOKEN', ''),
        ],

        'pagseguro' => [
            'email' => env('PAGSEGURO_EMAIL', ''),
            'token' => env('PAGSEGURO_TOKEN', ''),
            'sandbox' => env('PAGSEGURO_SANDBOX', true),
        ],

        'paypal' => [
            'client_id' => env('PAYPAL_CLIENT_ID', ''),
            'secret' => env('PAYPAL_SECRET', ''),
            'mode' => env('PAYPAL_MODE', 'sandbox'), // 'sandbox' ou 'live'
        ],

        'stripe' => [
            'key' => env('STRIPE_KEY', ''),
            'secret' => env('STRIPE_SECRET', ''),
        ],
    ],
];
