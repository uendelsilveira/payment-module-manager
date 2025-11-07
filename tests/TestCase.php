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
