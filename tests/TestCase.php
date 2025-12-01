<?php

namespace UendelSilveira\PaymentModuleManager\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase; // Reverted to RefreshDatabase
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use UendelSilveira\PaymentModuleManager\Providers\PaymentServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    use MockeryPHPUnitIntegration;
    use RefreshDatabase; // Reverted to RefreshDatabase

    /**
     * Get package providers.
     *
     * @param Application $app
     *
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            PaymentServiceProvider::class,
            \Laravel\Sanctum\SanctumServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param Application $app
     */
    protected function getEnvironmentSetUp($app): void
    {
        // Configura o banco de dados em memória para os testes.
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Configurações mínimas para o pacote funcionar.
        $app['config']->set('payment.default_gateway', 'mercadopago');
        $app['config']->set('logging.default', 'null');
    }

    /**
     * Define database migrations.
     *
     * @return void
     */
    protected function defineDatabaseMigrations()
    {
        // Este método é chamado pelo trait RefreshDatabase
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
