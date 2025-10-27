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
| As rotas neste arquivo são automaticamente prefixadas com 'api' e
| recebem o middleware 'api' quando carregadas pelo ServiceProvider.
|
*/

Route::prefix('api')
    ->middleware('api')
    ->group(function () {
        Route::post('payment/process', [PaymentController::class, 'process'])
            ->name('payment.process');

        Route::post('mercadopago/webhook', [MercadoPagoWebhookController::class, 'handle'])
            ->name('mercadopago.webhook');
    });
