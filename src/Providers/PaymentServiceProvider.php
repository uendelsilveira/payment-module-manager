<?php

namespace Us\PaymentModuleManager\Providers;

use Illuminate\Support\ServiceProvider;
use Us\PaymentModuleManager\Repositories\TransactionRepository;
use Us\PaymentModuleManager\Services\PaymentService;

class PaymentServiceProvider extends ServiceProvider
{
    /**
     * Registra bindings e singletons no container do Laravel.
     */
    public function register()
    {
        // Evita execução se o Laravel não estiver disponível
        if (! class_exists('Illuminate\Support\ServiceProvider')) {
            return;
        }

        // Mescla o arquivo de configuração (caso exista)
        $configPath = __DIR__.'/../../config/payment.php';

        if (file_exists($configPath)) {
            $this->mergeConfigFrom($configPath, 'payment');
        }

        // Bindings e singletons
        $this->app->bind(TransactionRepository::class, TransactionRepository::class);
        $this->app->singleton(PaymentService::class, fn () => new PaymentService);
    }

    /**
     * Executa ações após o registro (rotas, migrations, configs etc.)
     */
    public function boot(): void
    {
        if (! function_exists('base_path')) {
            // Não faz nada fora do contexto Laravel
            return;
        }

        $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        $this->publishes([
            __DIR__.'/../../config/payment.php' => config_path('payment.php'),
        ], 'config');
    }
}
