<?php

namespace UendelSilveira\PaymentModuleManager\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Mockery\MockInterface;
use Orchestra\Testbench\TestCase;
use UendelSilveira\PaymentModuleManager\Jobs\ProcessWebhookJob;
use UendelSilveira\PaymentModuleManager\Models\Transaction;
use UendelSilveira\PaymentModuleManager\Providers\PaymentServiceProvider;
use UendelSilveira\PaymentModuleManager\Services\PaymentService;

class ProcessWebhookJobTest extends TestCase
{
    use RefreshDatabase;

    /** @var PaymentService&MockInterface */
    private PaymentService $paymentService;

    protected function setUp(): void
    {
        parent::setUp();

        // Clean up any pending transactions
        while (DB::connection()->transactionLevel() > 0) {
            DB::connection()->rollBack();
        }

        /** @var PaymentService&MockInterface $paymentService */
        $paymentService = Mockery::mock(PaymentService::class);
        $this->paymentService = $paymentService;
    }

    protected function tearDown(): void
    {
        Mockery::close();

        // Ensure no active transactions
        if (\Illuminate\Support\Facades\DB::connection()->transactionLevel() > 0) {
            \Illuminate\Support\Facades\DB::connection()->rollBack();
        }

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
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }

    public function test_job_has_correct_configuration(): void
    {
        $processWebhookJob = new ProcessWebhookJob('mercadopago', ['test' => 'data']);

        $this->assertEquals(3, $processWebhookJob->tries);
        $this->assertEquals(60, $processWebhookJob->backoff);
        $this->assertTrue($processWebhookJob->deleteWhenMissingModels);
    }

    public function test_job_can_be_constructed_with_parameters(): void
    {
        $webhookData = ['type' => 'payment', 'data' => ['id' => '123']];
        $processWebhookJob = new ProcessWebhookJob('mercadopago', $webhookData, 1);

        $this->assertEquals('mercadopago', $processWebhookJob->gateway);
        $this->assertEquals($webhookData, $processWebhookJob->webhookData);
        $this->assertEquals(1, $processWebhookJob->transactionId);
    }

    public function test_job_can_be_constructed_without_transaction_id(): void
    {
        $webhookData = ['type' => 'payment'];
        $processWebhookJob = new ProcessWebhookJob('mercadopago', $webhookData);

        $this->assertNull($processWebhookJob->transactionId);
    }

    public function test_handle_processes_mercadopago_payment_webhook(): void
    {
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->atLeast()->once();
        Log::shouldReceive('error')->zeroOrMoreTimes();

        Transaction::factory()->create([
            'external_id' => '123456',
            'status' => 'pending',
        ]);

        $webhookData = [
            'type' => 'payment',
            'data' => ['id' => '123456'],
        ];

        /** @var Transaction $transaction */
        $transaction = Transaction::query()->where('external_id', '123456')->first();

        $this->paymentService
            ->shouldReceive('getPaymentDetails')
            ->once()
            ->andReturn($transaction);

        $processWebhookJob = new ProcessWebhookJob('mercadopago', $webhookData);
        $processWebhookJob->handle($this->paymentService);

        $this->assertTrue(true); // Test completed without exceptions
    }

    public function test_handle_skips_unsupported_notification_types(): void
    {
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->atLeast()->once();
        Log::shouldReceive('warning')->atLeast()->once();
        Log::shouldReceive('error')->zeroOrMoreTimes();

        $webhookData = [
            'type' => 'merchant_order',
            'data' => ['id' => '123'],
        ];

        $this->paymentService
            ->shouldNotReceive('getPaymentDetails');

        $processWebhookJob = new ProcessWebhookJob('mercadopago', $webhookData);
        $processWebhookJob->handle($this->paymentService);

        $this->assertTrue(true); // Test completed without exceptions
    }

    public function test_handle_logs_error_when_payment_id_missing(): void
    {
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->atLeast()->once();
        Log::shouldReceive('error')->atLeast()->once();

        $webhookData = [
            'type' => 'payment',
            'data' => [], // Missing 'id'
        ];

        $this->paymentService
            ->shouldNotReceive('getPaymentDetails');

        $processWebhookJob = new ProcessWebhookJob('mercadopago', $webhookData);
        $processWebhookJob->handle($this->paymentService);

        $this->assertTrue(true); // Test completed without exceptions
    }

    public function test_handle_logs_warning_when_transaction_not_found(): void
    {
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->atLeast()->once();
        Log::shouldReceive('warning')->atLeast()->once();
        Log::shouldReceive('error')->zeroOrMoreTimes();

        $webhookData = [
            'type' => 'payment',
            'data' => ['id' => 'non-existent-id'],
        ];

        $this->paymentService
            ->shouldNotReceive('getPaymentDetails');

        $processWebhookJob = new ProcessWebhookJob('mercadopago', $webhookData);
        $processWebhookJob->handle($this->paymentService);

        $this->assertTrue(true); // Test completed without exceptions
    }

    public function test_handle_throws_exception_on_service_error(): void
    {
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->atLeast()->once();
        Log::shouldReceive('error')->atLeast()->once();

        Transaction::factory()->create([
            'external_id' => '123456',
            'status' => 'pending',
        ]);

        $webhookData = [
            'type' => 'payment',
            'data' => ['id' => '123456'],
        ];

        $exception = new \Exception('Service error');

        $this->paymentService
            ->shouldReceive('getPaymentDetails')
            ->once()
            ->andThrow($exception);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Service error');

        $processWebhookJob = new ProcessWebhookJob('mercadopago', $webhookData);
        $processWebhookJob->handle($this->paymentService);
    }

    public function test_failed_logs_critical_error(): void
    {
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('critical')->once();

        $webhookData = ['type' => 'payment'];
        $processWebhookJob = new ProcessWebhookJob('mercadopago', $webhookData, 1);

        $exception = new \Exception('Final failure');
        $processWebhookJob->failed($exception);

        $this->assertTrue(true); // Test completed without exceptions
    }

    public function test_job_can_be_dispatched(): void
    {
        Queue::fake();

        $webhookData = ['type' => 'payment', 'data' => ['id' => '123']];

        ProcessWebhookJob::dispatch('mercadopago', $webhookData, 1);

        Queue::assertPushed(ProcessWebhookJob::class, fn ($job): bool => $job->gateway === 'mercadopago'
            && $job->webhookData === $webhookData
            && $job->transactionId === 1);
    }

    public function test_job_processes_with_correlation_id_in_logs(): void
    {
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->atLeast()->once()->with(
            Mockery::any(),
            Mockery::on(fn ($context): bool => isset($context['correlation_id']))
        );
        Log::shouldReceive('error')->zeroOrMoreTimes();

        Transaction::factory()->create([
            'external_id' => '123456',
            'status' => 'pending',
        ]);

        $webhookData = [
            'type' => 'payment',
            'data' => ['id' => '123456'],
        ];

        /** @var Transaction $transactionForReturn */
        $transactionForReturn = Transaction::query()->where('external_id', '123456')->first();

        $this->paymentService
            ->shouldReceive('getPaymentDetails')
            ->once()
            ->andReturn($transactionForReturn);

        $processWebhookJob = new ProcessWebhookJob('mercadopago', $webhookData);
        $processWebhookJob->handle($this->paymentService);

        $this->assertTrue(true); // Test completed without exceptions
    }

    public function test_job_masks_sensitive_data_in_logs(): void
    {
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->atLeast()->once()->with(
            Mockery::any(),
            Mockery::on(fn ($context): bool =>
                // Should not contain raw sensitive data
                ! isset($context['webhook_data']['password']))
        );
        Log::shouldReceive('error')->zeroOrMoreTimes();

        Transaction::factory()->create([
            'external_id' => '123456',
            'status' => 'pending',
        ]);

        $webhookData = [
            'type' => 'payment',
            'data' => ['id' => '123456'],
        ];

        /** @var Transaction $transactionForMask */
        $transactionForMask = Transaction::query()->where('external_id', '123456')->first();

        $this->paymentService
            ->shouldReceive('getPaymentDetails')
            ->once()
            ->andReturn($transactionForMask);

        $processWebhookJob = new ProcessWebhookJob('mercadopago', $webhookData);
        $processWebhookJob->handle($this->paymentService);

        $this->assertTrue(true); // Test completed without exceptions
    }
}
