<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 27/10/2025 13:59:40
*/

return [
    'default' => env('PAYMENT_PROVIDER', 'mercadopago'),
    'providers' => [
        'mercadopago' => [
            'client_id' => env('MERCADOPAGO_CLIENT_ID'),
            'client_secret' => env('MERCADOPAGO_CLIENT_SECRET'),
        ],
        // futuros provedores
    ],
];
