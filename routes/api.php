<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 28/10/2025 20:43:21
*/

use Illuminate\Support\Facades\Route;
use UendelSilveira\PaymentModuleManager\Http\Controllers\HealthCheckController;
use UendelSilveira\PaymentModuleManager\Http\Controllers\MercadoPagoWebhookController;
use UendelSilveira\PaymentModuleManager\Http\Controllers\PaymentController;
use UendelSilveira\PaymentModuleManager\Http\Controllers\ReportController;
use UendelSilveira\PaymentModuleManager\Http\Controllers\SettingsController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| IMPORTANTE: Para habilitar autenticação e autorização, descomente os
| middlewares abaixo e configure em config/payment.php
|
*/

// Health Check
Route::get('health', [HealthCheckController::class, 'check'])
    ->name('health.check');

// Rotas de Pagamento
Route::post('payment/process', [PaymentController::class, 'process'])
    ->middleware(['payment.idempotency', 'payment.rate_limit:payment_process'])
    // ->middleware(['payment.auth', 'payment.authorize:process-payment'])
    ->name('payment.process');

Route::get('payments/{transaction}', [PaymentController::class, 'show'])
    ->middleware(['payment.rate_limit:payment_query'])
    // ->middleware(['payment.auth', 'payment.authorize:view-payment'])
    ->name('payment.show');

Route::post('payments/{transaction}/refund', [PaymentController::class, 'refund'])
    ->middleware(['payment.rate_limit:payment_process'])
    // ->middleware(['payment.auth', 'payment.authorize:refund-payment'])
    ->name('payment.refund');

Route::post('payments/{transaction}/cancel', [PaymentController::class, 'cancel'])
    ->middleware(['payment.rate_limit:payment_process'])
    // ->middleware(['payment.auth', 'payment.authorize:cancel-payment'])
    ->name('payment.cancel');

// Rotas de Webhook
Route::post('mercadopago/webhook', [MercadoPagoWebhookController::class, 'handle'])
    ->middleware(['mercadopago.webhook.signature', 'payment.rate_limit:webhook'])
    ->name('mercadopago.webhook');

// Rotas de Configuração (SENSÍVEIS - Recomenda-se habilitar autenticação)
Route::prefix('settings')->group(function () {
    Route::get('mercadopago', [SettingsController::class, 'getMercadoPagoSettings'])
        ->middleware(['payment.rate_limit:settings'])
        // ->middleware(['payment.auth', 'payment.authorize:view-settings'])
        ->name('settings.mercadopago.get');

    Route::post('mercadopago', [SettingsController::class, 'saveMercadoPagoSettings'])
        ->middleware(['payment.rate_limit:settings'])
        // ->middleware(['payment.auth', 'payment.authorize:manage-settings'])
        ->name('settings.mercadopago.save');
});

// Rotas para o fluxo de conexão (OAuth 2.0)
Route::prefix('connect')->group(function () {
    Route::get('mercadopago', [SettingsController::class, 'redirectToMercadoPago'])
        // ->middleware(['payment.auth'])
        ->name('connect.mercadopago.redirect');

    Route::get('mercadopago/callback', [SettingsController::class, 'handleMercadoPagoCallback'])
        ->name('connect.mercadopago.callback');
});

// Rotas de Relatórios
Route::prefix('reports')->group(function () {
    Route::get('transactions/summary', [ReportController::class, 'transactionSummary'])
        ->middleware(['payment.rate_limit:payment_query'])
        // ->middleware(['payment.auth', 'payment.authorize:view-reports'])
        ->name('reports.transactions.summary');

    Route::get('transactions/methods', [ReportController::class, 'transactionsByMethod'])
        ->middleware(['payment.rate_limit:payment_query'])
        // ->middleware(['payment.auth', 'payment.authorize:view-reports'])
        ->name('reports.transactions.methods');
});
