<?php

namespace Us\PaymentModuleManager\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Us\PaymentModuleManager\Contracts\TransactionRepositoryInterface;
use Us\PaymentModuleManager\Repositories\TransactionRepository;
use Us\PaymentModuleManager\Services\GatewayManager;
use Us\PaymentModuleManager\Services\PaymentService;
use Us\PaymentModuleManager\Contracts\MercadoPagoClientInterface;
use Us\PaymentModuleManager\Services\MercadoPagoClient;

class PaymentServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Faz o merge do arquivo de configuração do pacote com o da aplicação
        $this->mergeConfigFrom(__DIR__.'/../../config/payment.php', 'payment');

        // Registra o binding da interface para a implementação do repositório
        $this->app->bind(TransactionRepositoryInterface::class, TransactionRepository::class);

        // Registra o binding da interface para a implementação do cliente Mercado Pago
        $this->app->singleton(MercadoPagoClientInterface::class, MercadoPagoClient::class);

        // Registra o GatewayManager como um singleton
        $this->app->singleton(GatewayManager::class, function ($app) {
            return new GatewayManager();
        });

        // Registra o PaymentService como um singleton
        $this->app->singleton(PaymentService::class, function ($app) {
            return new PaymentService(
                $app->make(GatewayManager::class),
                $app->make(TransactionRepositoryInterface::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Carrega as rotas da API do pacote (o prefixo e middleware já estão no arquivo de rotas)
        $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');

        // Carrega as migrations do pacote
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        // Permite que o usuário publique o arquivo de configuração
        $this->publishes([
            __DIR__.'/../../config/payment.php' => config_path('payment.php'),
        ], 'config');
    }
}
