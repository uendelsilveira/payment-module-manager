<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 28/10/2025 20:43:21
*/

use Illuminate\Support\Facades\Route;
use UendelSilveira\PaymentModuleManager\Http\Controllers\MercadoPagoWebhookController;
use UendelSilveira\PaymentModuleManager\Http\Controllers\PaymentController;
use UendelSilveira\PaymentModuleManager\Http\Controllers\SettingsController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Rotas de Pagamento
Route::post('payment/process', [PaymentController::class, 'process'])
    ->name('payment.process');

// Rotas de Webhook
Route::post('mercadopago/webhook', [MercadoPagoWebhookController::class, 'handle'])
    ->name('mercadopago.webhook')
    ->middleware('mercadopago.webhook.signature');

// Rotas de Configuração
Route::prefix('settings')->group(function () {
    Route::get('mercadopago', [SettingsController::class, 'getMercadoPagoSettings'])->name('settings.mercadopago.get');
    Route::post('mercadopago', [SettingsController::class, 'saveMercadoPagoSettings'])->name('settings.mercadopago.save');
});
