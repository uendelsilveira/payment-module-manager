<?php

namespace UendelSilveira\PaymentModuleManager\Tests\Unit;

use Illuminate\Support\Facades\DB;
use Mockery;
use Mockery\MockInterface;
use Orchestra\Testbench\TestCase;
use UendelSilveira\PaymentModuleManager\Contracts\MercadoPagoClientInterface;
use UendelSilveira\PaymentModuleManager\Http\Controllers\HealthCheckController;
use UendelSilveira\PaymentModuleManager\Providers\PaymentServiceProvider;

class HealthCheckControllerTest extends TestCase
{
    private HealthCheckController $healthCheckController;

    private \Mockery\MockInterface&\UendelSilveira\PaymentModuleManager\Contracts\MercadoPagoClientInterface $mercadoPagoClient;

    protected function setUp(): void
    {
        parent::setUp();
        /** @var MockInterface&MercadoPagoClientInterface $mercadoPagoClient */
        $mercadoPagoClient = Mockery::mock(MercadoPagoClientInterface::class);
        $this->mercadoPagoClient = $mercadoPagoClient;

        $this->healthCheckController = new HealthCheckController($this->mercadoPagoClient);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [PaymentServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', ['driver' => 'sqlite', 'database' => ':memory:']);
        $app['config']->set('cache.default', 'array');
    }

    public function test_check_returns_healthy_status_when_all_checks_pass(): void
    {
        $this->mercadoPagoClient->shouldReceive('getPaymentMethods')->once()->andReturn([]);
        DB::connection()->getSchemaBuilder()->create('transactions', fn ($table) => $table->id());

        $jsonResponse = $this->healthCheckController->check();
        $this->assertEquals(200, $jsonResponse->getStatusCode());
        $responseData = $jsonResponse->getData(true);
        $data = $responseData['data'];

        $this->assertEquals('healthy', $data['status']);
        $this->assertEquals('healthy', $data['checks']['database']['status']);
        $this->assertEquals('healthy', $data['checks']['cache']['status']);
        $this->assertEquals('healthy', $data['checks']['mercadopago_api']['status']);
    }

    public function test_check_returns_degraded_status_when_mercadopago_fails(): void
    {
        $this->mercadoPagoClient->shouldReceive('getPaymentMethods')->once()->andThrow(new \Exception('API connection failed'));
        DB::connection()->getSchemaBuilder()->create('transactions', fn ($table) => $table->id());

        $jsonResponse = $this->healthCheckController->check();
        $this->assertEquals(503, $jsonResponse->getStatusCode());
        $responseData = $jsonResponse->getData(true);
        $data = $responseData['data'];

        $this->assertEquals('degraded', $data['status']);
        $this->assertEquals('unhealthy', $data['checks']['mercadopago_api']['status']);
        $message = $data['checks']['mercadopago_api']['message'] ?? '';
        $this->assertStringContainsString('failed', is_string($message) ? $message : '');
    }
}
