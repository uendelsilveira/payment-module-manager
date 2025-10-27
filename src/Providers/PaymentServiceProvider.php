<?php

namespace Us\PaymentModuleManager\Providers;

use Illuminate\Support\ServiceProvider;
use Us\PaymentModuleManager\Repositories\TransactionRepository;
use Us\PaymentModuleManager\Contracts\TransactionRepositoryInterface;
use Us\PaymentModuleManager\Services\PaymentService;

class PaymentServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/payment.php', 'payment');
        $this->app->bind(TransactionRepositoryInterface::class, TransactionRepository::class);
        $this->app->singleton(PaymentService::class, fn() => new PaymentService());
    }

    public function boot()
    {
        $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->publishes([
            __DIR__.'/../../config/payment.php' => config_path('payment.php'),
        ], 'config');
    }
}
