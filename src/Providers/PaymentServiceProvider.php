<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 28/10/2025 20:43:21
*/

namespace UendelSilveira\PaymentModuleManager\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use UendelSilveira\PaymentModuleManager\Contracts\MercadoPagoClientInterface;
use UendelSilveira\PaymentModuleManager\Contracts\TransactionRepositoryInterface;
use UendelSilveira\PaymentModuleManager\Http\Middleware\VerifyMercadoPagoSignature;
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
        $this->app->singleton(MercadoPagoClientInterface::class, MercadoPagoClient::class);
        $this->app->singleton(GatewayManager::class, fn () => new GatewayManager);
        $this->app->singleton(PaymentService::class, fn ($app) => new PaymentService(
            $app->make(GatewayManager::class),
            $app->make(TransactionRepositoryInterface::class)
        ));
    }

    public function boot(): void
    {
        // Registra o alias do middleware
        $router = $this->app->make('router');
        $router->aliasMiddleware('mercadopago.webhook.signature', VerifyMercadoPagoSignature::class);

        Route::prefix('api')
            ->middleware('api')
            ->group(function () {
                $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');
            });

        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->publishes([
            __DIR__.'/../../config/payment.php' => config_path('payment.php'),
        ], 'config');
    }
}
