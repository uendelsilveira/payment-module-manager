<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 19/11/2025 14:24:12
*/

namespace UendelSilveira\PaymentModuleManager\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use UendelSilveira\PaymentModuleManager\Console\Commands\ReprocessFailedPaymentsCommand;
use UendelSilveira\PaymentModuleManager\Contracts\GatewayRepositoryInterface;
use UendelSilveira\PaymentModuleManager\Contracts\SettingsRepositoryInterface;
use UendelSilveira\PaymentModuleManager\Contracts\TransactionRepositoryInterface;
use UendelSilveira\PaymentModuleManager\Contracts\WebhookLogRepositoryInterface; // Adicionado
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
use UendelSilveira\PaymentModuleManager\Repositories\GatewayRepository;
use UendelSilveira\PaymentModuleManager\Repositories\SettingsRepository;
use UendelSilveira\PaymentModuleManager\Repositories\TransactionRepository;
use UendelSilveira\PaymentModuleManager\Repositories\WebhookLogRepository; // Adicionado
use UendelSilveira\PaymentModuleManager\Services\PaymentService;
use UendelSilveira\PaymentModuleManager\Services\RetryService;

class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/payment.php', 'payment');

        // Registrar o PaymentGatewayManager como singleton, injetando a configuração.
        $this->app->singleton(PaymentGatewayManager::class, fn ($app): \UendelSilveira\PaymentModuleManager\PaymentGatewayManager => new PaymentGatewayManager($app['config']['payment']));

        // Bind das interfaces às suas implementações concretas.
        $this->app->bind(TransactionRepositoryInterface::class, TransactionRepository::class);
        $this->app->bind(SettingsRepositoryInterface::class, SettingsRepository::class);
        $this->app->bind(GatewayRepositoryInterface::class, GatewayRepository::class);
        $this->app->bind(WebhookLogRepositoryInterface::class, WebhookLogRepository::class); // Adicionado

        // Registrar o PaymentService, que depende do Manager e do Repository.
        $this->app->singleton(PaymentService::class, fn ($app): \UendelSilveira\PaymentModuleManager\Services\PaymentService => new PaymentService(
            $app->make(PaymentGatewayManager::class),
            $app->make(TransactionRepositoryInterface::class),
            $app->make(RetryService::class)
        ));
    }

    public function boot(): void
    {
        // Publicar o arquivo de configuração.
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/payment.php' => config_path('payment.php'),
            ], 'config');

            $this->commands([ReprocessFailedPaymentsCommand::class]);
        }

        // Registrar eventos e listeners.
        Event::listen(PaymentProcessed::class, LogPaymentProcessed::class);
        Event::listen(PaymentFailed::class, LogPaymentFailed::class);
        Event::listen(PaymentStatusChanged::class, [
            LogPaymentStatusChanged::class,
            SendPaymentStatusNotification::class,
        ]);

        // Carregar factories para testes.
        if ($this->app->runningUnitTests()) {
            $this->loadFactoriesFrom(__DIR__.'/../../database/factories');
        }

        // Registrar middlewares.
        $router = $this->app->make('router');
        $router->aliasMiddleware('payment.resolve', ResolvePaymentGateway::class);
        $router->aliasMiddleware('payment.auth', AuthenticatePaymentRequest::class);
        $router->aliasMiddleware('payment.authorize', AuthorizePaymentAction::class);
        $router->aliasMiddleware('payment.rate_limit', RateLimitPaymentRequests::class);
        $router->aliasMiddleware('payment.idempotency', EnsureIdempotency::class);

        // Carregar rotas e migrações.
        Route::prefix('api')
            ->middleware('api')
            ->group(fn () => $this->loadRoutesFrom(__DIR__.'/../../routes/api.php'));

        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }
}
