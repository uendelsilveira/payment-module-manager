<?php

use Illuminate\Support\Facades\Route;
use UendelSilveira\PaymentModuleManager\Http\Controllers\HealthCheckController;
use UendelSilveira\PaymentModuleManager\Http\Controllers\PaymentController;
use UendelSilveira\PaymentModuleManager\Http\Controllers\ReportController;
use UendelSilveira\PaymentModuleManager\Http\Controllers\SettingsController;
use UendelSilveira\PaymentModuleManager\Http\Middleware\WebhookValidationMiddleware;

// Health Check
Route::get('health', [HealthCheckController::class, 'check'])
    ->name('health.check');

// Payment Routes
Route::post('payment/process', [PaymentController::class, 'process'])
    ->middleware(['payment.resolve', 'payment.idempotency', 'payment.rate_limit:payment_process'])
    ->name('payment.process');

Route::get('payments/{transaction}', [PaymentController::class, 'show'])
    ->middleware(['payment.rate_limit:payment_query'])
    ->name('payment.show');

Route::post('payments/{transaction}/refund', [PaymentController::class, 'refund'])
    ->middleware(['payment.resolve', 'payment.rate_limit:payment_process'])
    ->name('payment.refund');

Route::post('payments/{transaction}/cancel', [PaymentController::class, 'cancel'])
    ->middleware(['payment.resolve', 'payment.rate_limit:payment_process'])
    ->name('payment.cancel');

// Webhook Route
Route::post('payment/webhook/{gateway}', [PaymentController::class, 'handleWebhook'])
    ->middleware([WebhookValidationMiddleware::class, 'payment.rate_limit:webhook'])
    ->name('payment.webhook');

// Settings Routes
Route::prefix('settings/{gateway}')->group(function () {
    Route::get('/', [SettingsController::class, 'getSettings'])
        ->middleware(['payment.rate_limit:settings'])
        ->name('settings.gateway.get');

    Route::post('/', [SettingsController::class, 'saveSettings'])
        ->middleware(['payment.rate_limit:settings'])
        ->name('settings.gateway.save');
});

// Connect Routes
Route::prefix('connect/{gateway}')->group(function () {
    Route::get('/', [SettingsController::class, 'redirectToGateway'])
        ->name('connect.gateway.redirect');

    Route::get('callback', [SettingsController::class, 'handleGatewayCallback'])
        ->name('connect.gateway.callback');
});

// Report Routes
Route::prefix('reports')->group(function () {
    Route::get('transactions/summary', [ReportController::class, 'transactionSummary'])
        ->middleware(['payment.rate_limit:payment_query'])
        ->name('reports.transactions.summary');

    Route::get('transactions/methods', [ReportController::class, 'transactionsByMethod'])
        ->middleware(['payment.rate_limit:payment_query'])
        ->name('reports.transactions.methods');
});
