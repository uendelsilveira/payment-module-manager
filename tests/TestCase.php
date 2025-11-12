<?php

namespace UendelSilveira\PaymentModuleManager\Tests;

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use UendelSilveira\PaymentModuleManager\Providers\PaymentServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    use MockeryPHPUnitIntegration;

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

        // Gateway padrão quando não especificado
        $app['config']->set('payment.default_gateway', 'mercadopago');

        // Canais de log mínimos usados pelo pacote durante os testes
        $app['config']->set('logging.default', 'single');
        $app['config']->set('logging.channels.single', [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => 'debug',
            'replace_placeholders' => true,
        ]);
        $app['config']->set('logging.channels.payment', [
            'driver' => 'single',
            'path' => storage_path('logs/testing-payment.log'),
            'level' => 'debug',
            'replace_placeholders' => true,
        ]);
        $app['config']->set('logging.channels.transaction', [
            'driver' => 'single',
            'path' => storage_path('logs/testing-transaction.log'),
            'level' => 'debug',
            'replace_placeholders' => true,
        ]);
        $app['config']->set('logging.channels.webhook', [
            'driver' => 'single',
            'path' => storage_path('logs/testing-webhook.log'),
            'level' => 'debug',
            'replace_placeholders' => true,
        ]);
        $app['config']->set('logging.channels.gateway', [
            'driver' => 'single',
            'path' => storage_path('logs/testing-gateway.log'),
            'level' => 'debug',
            'replace_placeholders' => true,
        ]);

        // Rota dummy para webhooks usada em tests
        $app['router']->post('/__test/webhook', fn () => response()->json(['ok' => true]))->name('payment.webhook');
    }
}
