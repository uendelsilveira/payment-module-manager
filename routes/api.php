<?php

use Illuminate\Support\Facades\Route;
use Us\PaymentModuleManager\Http\Controllers\PaymentController;
use Us\PaymentModuleManager\Http\Controllers\MercadoPagoWebhookController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Aqui é onde você pode registrar as rotas da API para o seu pacote.
|
*/

Route::post('payment/process', [PaymentController::class, 'process'])
    ->name('payment.process');

Route::post('mercadopago/webhook', [MercadoPagoWebhookController::class, 'handle'])
    ->name('mercadopago.webhook');
