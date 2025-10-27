<?php
	
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
