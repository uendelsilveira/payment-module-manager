<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 28/10/2025 20:43:21
*/

namespace Us\PaymentModuleManager\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Us\PaymentModuleManager\Providers\PaymentServiceProvider;

class TestCase extends OrchestraTestCase
{
    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Executa as migrations do pacote em um banco de dados em memória.
        $this->artisan('migrate', [
            '--database' => 'testing',
            '--realpath' => realpath(__DIR__.'/../database/migrations'),
        ]);
    }

    /**
     * Carrega os Service Providers do pacote.
     *
     * @param \Illuminate\Foundation\Application $app
     *
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            PaymentServiceProvider::class,
        ];
    }

    /**
     * Define a configuração do ambiente.
     *
     * @param \Illuminate\Foundation\Application $app
     */
    protected function getEnvironmentSetUp($app)
    {
        // Configura o banco de dados em memória para os testes.
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}
