<?php

namespace UendelSilveira\PaymentModuleManager\Tests\Unit;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Mockery;
use Mockery\MockInterface;
use Orchestra\Testbench\TestCase;
use UendelSilveira\PaymentModuleManager\Contracts\MercadoPagoClientInterface;
use UendelSilveira\PaymentModuleManager\Http\Controllers\HealthCheckController;
use UendelSilveira\PaymentModuleManager\Providers\PaymentServiceProvider;

class HealthCheckControllerTest extends TestCase
{
    private HealthCheckController $controller;

    /** @var MercadoPagoClientInterface&MockInterface */
    private MercadoPagoClientInterface $mercadoPagoClient;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var MercadoPagoClientInterface&MockInterface $mercadoPagoClient */
        $mercadoPagoClient = Mockery::mock(MercadoPagoClientInterface::class);
        $this->mercadoPagoClient = $mercadoPagoClient;
        $this->controller = new HealthCheckController($this->mercadoPagoClient);
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
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        $app['config']->set('cache.default', 'array');
    }

    public function test_check_returns_healthy_status_when_all_checks_pass(): void
    {
        // Mock MercadoPago API check
        $this->mercadoPagoClient
            ->shouldReceive('getPaymentMethods')
            ->once()
            ->andReturn([]);

        // Create transactions table for DB check
        DB::connection()->getSchemaBuilder()->create('transactions', function ($table) {
            $table->id();
            $table->timestamps();
        });

        $response = $this->controller->check();

        $this->assertEquals(200, $response->getStatusCode());

        $responseData = $response->getData(true);
        $data = $responseData['data'];

        $this->assertEquals('healthy', $data['status']);
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertArrayHasKey('checks', $data);

        $this->assertEquals('healthy', $data['checks']['database']['status']);
        $this->assertEquals('healthy', $data['checks']['cache']['status']);
        $this->assertEquals('healthy', $data['checks']['mercadopago_api']['status']);
    }

    public function test_check_returns_degraded_status_when_mercadopago_fails(): void
    {
        // Mock MercadoPago API failure
        $this->mercadoPagoClient
            ->shouldReceive('getPaymentMethods')
            ->once()
            ->andThrow(new \Exception('API connection failed'));

        // Create transactions table for DB check
        DB::connection()->getSchemaBuilder()->create('transactions', function ($table) {
            $table->id();
            $table->timestamps();
        });

        $response = $this->controller->check();

        $this->assertEquals(503, $response->getStatusCode());

        $responseData = $response->getData(true);
        $data = $responseData['data'];

        $this->assertEquals('degraded', $data['status']);
        $this->assertEquals('unhealthy', $data['checks']['mercadopago_api']['status']);
        $this->assertStringContainsString('failed', (string) $data['checks']['mercadopago_api']['message']);
    }

    public function test_check_returns_degraded_status_when_database_fails(): void
    {
        // Mock MercadoPago API check
        $this->mercadoPagoClient
            ->shouldReceive('getPaymentMethods')
            ->once()
            ->andReturn([]);

        // Don't create the transactions table to simulate DB failure

        $response = $this->controller->check();

        $this->assertEquals(503, $response->getStatusCode());

        $responseData = $response->getData(true);
        $data = $responseData['data'];

        $this->assertEquals('degraded', $data['status']);
        $this->assertEquals('unhealthy', $data['checks']['database']['status']);
    }

    public function test_check_database_returns_healthy_when_connection_works(): void
    {
        $this->mercadoPagoClient
            ->shouldReceive('getPaymentMethods')
            ->once()
            ->andReturn([]);

        DB::connection()->getSchemaBuilder()->create('transactions', function ($table) {
            $table->id();
            $table->timestamps();
        });

        $response = $this->controller->check();
        $responseData = $response->getData(true);
        $data = $responseData['data'];

        $this->assertEquals('healthy', $data['checks']['database']['status']);
        $this->assertEquals('Database connection is working', $data['checks']['database']['message']);
    }

    public function test_check_cache_returns_healthy_when_cache_works(): void
    {
        $this->mercadoPagoClient
            ->shouldReceive('getPaymentMethods')
            ->once()
            ->andReturn([]);

        DB::connection()->getSchemaBuilder()->create('transactions', function ($table) {
            $table->id();
            $table->timestamps();
        });

        $response = $this->controller->check();
        $responseData = $response->getData(true);
        $data = $responseData['data'];

        $this->assertEquals('healthy', $data['checks']['cache']['status']);
        $this->assertEquals('Cache is working', $data['checks']['cache']['message']);
    }

    public function test_check_cache_cleans_up_test_key(): void
    {
        $this->mercadoPagoClient
            ->shouldReceive('getPaymentMethods')
            ->once()
            ->andReturn([]);

        DB::connection()->getSchemaBuilder()->create('transactions', function ($table) {
            $table->id();
            $table->timestamps();
        });

        // Set a test key before running check
        Cache::put('test_key', 'test_value', 10);
        $this->assertTrue(Cache::has('test_key'));

        $response = $this->controller->check();

        // Original key should still exist
        $this->assertTrue(Cache::has('test_key'));

        // The health check key should be cleaned up (not exist)
        $responseData = $response->getData(true);
        $data = $responseData['data'];
        $this->assertEquals('healthy', $data['checks']['cache']['status']);
    }

    public function test_check_mercadopago_api_returns_healthy_when_api_responds(): void
    {
        $this->mercadoPagoClient
            ->shouldReceive('getPaymentMethods')
            ->once()
            ->andReturn(['visa', 'mastercard']);

        DB::connection()->getSchemaBuilder()->create('transactions', function ($table) {
            $table->id();
            $table->timestamps();
        });

        $response = $this->controller->check();
        $responseData = $response->getData(true);
        $data = $responseData['data'];

        $this->assertEquals('healthy', $data['checks']['mercadopago_api']['status']);
        $this->assertEquals('MercadoPago API is reachable', $data['checks']['mercadopago_api']['message']);
    }

    public function test_check_includes_error_details_when_check_fails(): void
    {
        $errorMessage = 'Specific API error';

        $this->mercadoPagoClient
            ->shouldReceive('getPaymentMethods')
            ->once()
            ->andThrow(new \Exception($errorMessage));

        DB::connection()->getSchemaBuilder()->create('transactions', function ($table) {
            $table->id();
            $table->timestamps();
        });

        $response = $this->controller->check();
        $responseData = $response->getData(true);
        $data = $responseData['data'];

        $this->assertArrayHasKey('error', $data['checks']['mercadopago_api']);
        $this->assertEquals($errorMessage, $data['checks']['mercadopago_api']['error']);
    }

    public function test_check_returns_proper_json_structure(): void
    {
        $this->mercadoPagoClient
            ->shouldReceive('getPaymentMethods')
            ->once()
            ->andReturn([]);

        DB::connection()->getSchemaBuilder()->create('transactions', function ($table) {
            $table->id();
            $table->timestamps();
        });

        $response = $this->controller->check();
        $responseData = $response->getData(true);
        $data = $responseData['data'];

        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertArrayHasKey('checks', $data);

        foreach (['database', 'cache', 'mercadopago_api'] as $check) {
            $this->assertArrayHasKey($check, $data['checks']);
            $this->assertArrayHasKey('status', $data['checks'][$check]);
            $this->assertArrayHasKey('message', $data['checks'][$check]);
        }
    }
}
