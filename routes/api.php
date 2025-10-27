<?php

use Illuminate\Support\Facades\Route;
use Us\PaymentModuleManager\Http\Controllers\PaymentController;
use Us\PaymentModuleManager\Http\Controllers\MercadoPagoWebhookController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Estas rotas são carregadas pelo PaymentServiceProvider e devem ser
| prefixadas e ter o middleware 'api' aplicado lá.
|
*/

Route::post('payment/process', [PaymentController::class, 'process'])
    ->name('payment.process');

Route::post('mercadopago/webhook', [MercadoPagoWebhookController::class, 'handle'])
    ->name('mercadopago.webhook');
