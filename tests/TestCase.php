<?php

namespace UendelSilveira\PaymentModuleManager\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use UendelSilveira\PaymentModuleManager\Providers\PaymentServiceProvider;

class TestCase extends OrchestraTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function getPackageProviders($app)
    {
        return [
            PaymentServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Configure logging channels for testing
        $app['config']->set('logging.channels.payment', [
            'driver' => 'single',
            'path' => storage_path('logs/payment-test.log'),
            'level' => 'debug',
        ]);
        $app['config']->set('logging.channels.webhook', [
            'driver' => 'single',
            'path' => storage_path('logs/webhook-test.log'),
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
    }
}
