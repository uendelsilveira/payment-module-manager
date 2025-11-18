<?php

namespace UendelSilveira\PaymentModuleManager\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use UendelSilveira\PaymentModuleManager\Console\Commands\ReprocessFailedPayments;
use UendelSilveira\PaymentModuleManager\Contracts\SettingsRepositoryInterface;
use UendelSilveira\PaymentModuleManager\Contracts\TransactionRepositoryInterface;
use UendelSilveira\PaymentModuleManager\Events\PaymentFailed;
use UendelSilveira\PaymentModuleManager\Events\PaymentProcessed;
use UendelSilveira\PaymentModuleManager\Events\PaymentStatusChanged;
use UendelSilveira\PaymentModuleManager\Http\Middleware\AuthenticatePaymentRequest;
use UendelSilveira\PaymentModuleManager\Http\Middleware\AuthorizePaymentAction;
use UendelSilveira\PaymentModuleManager\Http\Middleware\EnsureIdempotency;
use UendelSilveira\PaymentModuleManager\Http\Middleware\RateLimitPaymentRequests;
use UendelSilveira\PaymentModuleManager\Http\Middleware\ResolvePaymentGateway;
use UendelSilveira\PaymentModuleManager\Listeners\LogPaymentFailed;
use UendelSilveira\PaymentModuleManager\Listeners\LogPaymentProcessed;
use UendelSilveira\PaymentModuleManager\Listeners\LogPaymentStatusChanged;
use UendelSilveira\PaymentModuleManager\Listeners\SendPaymentStatusNotification;
use UendelSilveira\PaymentModuleManager\PaymentGatewayManager;
use UendelSilveira\PaymentModuleManager\Repositories\SettingsRepository;
use UendelSilveira\PaymentModuleManager\Repositories\TransactionRepository;
use UendelSilveira\PaymentModuleManager\Services\PaymentService;

class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/payment.php', 'payment');
        $this->app->singleton(PaymentGatewayManager::class);
        $this->app->bind(TransactionRepositoryInterface::class, TransactionRepository::class);
        $this->app->bind(SettingsRepositoryInterface::class, SettingsRepository::class);

        // Registrar o PaymentGatewayManager
        $this->app->singleton(
            PaymentGatewayManager::class,
            fn ($app): PaymentGatewayManager => new PaymentGatewayManager($app['config']['payment'])
        );

        $this->app->singleton(PaymentService::class, fn ($app): PaymentService => new PaymentService(
            $app->make(PaymentGatewayManager::class),
            $app->make(TransactionRepositoryInterface::class)
        ));
    }

    public function boot(): void
    {
        $this->publishes([__DIR__.'/../config/payment.php' => config_path('payment.php')], 'config');
        Event::listen(PaymentProcessed::class, LogPaymentProcessed::class);
        Event::listen(PaymentFailed::class, LogPaymentFailed::class);
        Event::listen(PaymentStatusChanged::class, [
            LogPaymentStatusChanged::class,
            SendPaymentStatusNotification::class,
        ]);

        if ($this->app->runningUnitTests()) {
            $this->loadFactoriesFrom(__DIR__.'/../../database/factories');
        }

        $router = $this->app->make('router');
        $router->aliasMiddleware('payment.resolve', ResolvePaymentGateway::class);
        $router->aliasMiddleware('payment.auth', AuthenticatePaymentRequest::class);
        $router->aliasMiddleware('payment.authorize', AuthorizePaymentAction::class);
        $router->aliasMiddleware('payment.rate_limit', RateLimitPaymentRequests::class);
        $router->aliasMiddleware('payment.idempotency', EnsureIdempotency::class);

        if ($this->app->runningInConsole()) {
            $this->commands([ReprocessFailedPayments::class]);
        }

        Route::prefix('api')
            ->middleware('api')
            ->group(fn () => $this->loadRoutesFrom(__DIR__.'/../../routes/api.php'));

        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        $configPath = function_exists('config_path')
            ? config_path('payment.php')
            : __DIR__.'/../../config/payment.php';

        $this->publishes([
            __DIR__.'/../../config/payment.php' => $configPath,
        ], 'config');
    }
}
