<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 04/11/2025 16:09:38
*/

namespace UendelSilveira\PaymentModuleManager\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use UendelSilveira\PaymentModuleManager\Console\Commands\ReprocessFailedPayments;
use UendelSilveira\PaymentModuleManager\Contracts\MercadoPagoClientInterface;
use UendelSilveira\PaymentModuleManager\Contracts\SettingsRepositoryInterface;
use UendelSilveira\PaymentModuleManager\Contracts\TransactionRepositoryInterface;
use UendelSilveira\PaymentModuleManager\Events\PaymentFailed;
use UendelSilveira\PaymentModuleManager\Events\PaymentProcessed;
use UendelSilveira\PaymentModuleManager\Events\PaymentStatusChanged;
use UendelSilveira\PaymentModuleManager\Http\Middleware\AuthenticatePaymentRequest;
use UendelSilveira\PaymentModuleManager\Http\Middleware\AuthorizePaymentAction;
use UendelSilveira\PaymentModuleManager\Http\Middleware\EnsureIdempotency;
use UendelSilveira\PaymentModuleManager\Http\Middleware\RateLimitPaymentRequests;
use UendelSilveira\PaymentModuleManager\Http\Middleware\VerifyMercadoPagoSignature;
use UendelSilveira\PaymentModuleManager\Listeners\LogPaymentFailed;
use UendelSilveira\PaymentModuleManager\Listeners\LogPaymentProcessed;
use UendelSilveira\PaymentModuleManager\Listeners\LogPaymentStatusChanged;
use UendelSilveira\PaymentModuleManager\Listeners\SendPaymentStatusNotification;
use UendelSilveira\PaymentModuleManager\Repositories\SettingsRepository;
use UendelSilveira\PaymentModuleManager\Repositories\TransactionRepository;
use UendelSilveira\PaymentModuleManager\Services\GatewayManager;
use UendelSilveira\PaymentModuleManager\Services\MercadoPagoClient;
use UendelSilveira\PaymentModuleManager\Services\PaymentService;

class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/payment.php', 'payment');
        $this->app->bind(TransactionRepositoryInterface::class, TransactionRepository::class);
        $this->app->bind(SettingsRepositoryInterface::class, SettingsRepository::class);
        $this->app->singleton(MercadoPagoClientInterface::class, MercadoPagoClient::class);
        $this->app->singleton(GatewayManager::class, fn (): \UendelSilveira\PaymentModuleManager\Services\GatewayManager => new GatewayManager);
        $this->app->singleton(PaymentService::class, fn ($app): \UendelSilveira\PaymentModuleManager\Services\PaymentService => new PaymentService(
            $app->make(GatewayManager::class),
            $app->make(TransactionRepositoryInterface::class)
        ));
    }

    public function boot(): void
    {
        // Register event listeners
        Event::listen(PaymentProcessed::class, LogPaymentProcessed::class);
        Event::listen(PaymentFailed::class, LogPaymentFailed::class);
        Event::listen(PaymentStatusChanged::class, LogPaymentStatusChanged::class);
        Event::listen(PaymentStatusChanged::class, SendPaymentStatusNotification::class);

        // Carrega as factories do pacote para que possam ser usadas nos testes.
        if ($this->app->runningUnitTests()) {
            $this->loadFactoriesFrom(__DIR__.'/../../database/factories');
        }

        // Registra os aliases dos middlewares
        $router = $this->app->make('router');
        $router->aliasMiddleware('mercadopago.webhook.signature', VerifyMercadoPagoSignature::class);
        $router->aliasMiddleware('payment.auth', AuthenticatePaymentRequest::class);
        $router->aliasMiddleware('payment.authorize', AuthorizePaymentAction::class);
        $router->aliasMiddleware('payment.rate_limit', RateLimitPaymentRequests::class);
        $router->aliasMiddleware('payment.idempotency', EnsureIdempotency::class);

        // Registra os comandos Artisan
        if ($this->app->runningInConsole()) {
            $this->commands([
                ReprocessFailedPayments::class,
            ]);
        }

        Route::prefix('api')
            ->middleware('api')
            ->group(function (): void {
                $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');
            });

        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->publishes([
            __DIR__.'/../../config/payment.php' => config_path('payment.php'),
        ], 'config');
    }
}
