<?php

namespace UendelSilveira\PaymentModuleManager\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use UendelSilveira\PaymentModuleManager\Providers\PaymentServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [PaymentServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Configuração de logs para ambiente de teste
        $app['config']->set('logging.channels.payment', [
            'driver' => 'single',
            'path' => storage_path('logs/payment-test.log'),
            'level' => 'debug',
        ]);

        $app['config']->set('logging.channels.gateway', [
            'driver' => 'single',
            'path' => storage_path('logs/gateway-test.log'),
            'level' => 'debug',
        ]);

        $app['config']->set('logging.channels.transaction', [
            'driver' => 'single',
            'path' => storage_path('logs/transaction-test.log'),
            'level' => 'debug',
        ]);

        $app['config']->set('logging.channels.webhook', [
            'driver' => 'single',
            'path' => storage_path('logs/webhook-test.log'),
            'level' => 'debug',
        ]);

        // Configuração do gateway Mercado Pago para testes
        $app['config']->set('payment.gateways.mercadopago.access_token', 'test-access-token');
        $app['config']->set('payment.gateways.mercadopago.base_url', 'https://api.mercadopago.com');
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Carrega migrações e factories apenas se existirem
        $migrations = __DIR__.'/../database/migrations';

        if (is_dir($migrations)) {
            $this->loadMigrationsFrom($migrations);
        }

        $factories = __DIR__.'/../database/factories';

        if (is_dir($factories)) {
            $this->withFactories($factories);
        }
    }
}
