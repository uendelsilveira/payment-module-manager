<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 28/10/2025 20:43:21
*/

use Illuminate\Support\Facades\Route;
use Us\PaymentModuleManager\Http\Controllers\MercadoPagoWebhookController;
use Us\PaymentModuleManager\Http\Controllers\PaymentController;

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
    ->name('mercadopago.webhook')
    ->middleware('mercadopago.webhook.signature'); // Aplica o middleware de verificação de assinatura
