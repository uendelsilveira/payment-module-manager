<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Configurações do Gateway de Pagamento
    |--------------------------------------------------------------------------
    */

    'gateways' => [
        'mercadopago' => [
            'public_key' => env('MERCADOPAGO_PUBLIC_KEY', ''),
            'access_token' => env('MERCADOPAGO_ACCESS_TOKEN', ''),

            /*
            |--------------------------------------------------------------------------
            | URL de notificação (Webhook)
            |--------------------------------------------------------------------------
            | Define a URL que o Mercado Pago chamará para enviar notificações
            | de status de pagamento. Por padrão, usa o APP_URL do projeto Laravel.
            */
            'notification_url' => env(
                'MERCADOPAGO_NOTIFICATION_URL',
                rtrim(config('app.url'), '/').'/api/mercadopago/webhook'
            ),
        ],
    ],
];
