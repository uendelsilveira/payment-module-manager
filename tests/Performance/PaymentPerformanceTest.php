<?php

namespace UendelSilveira\PaymentModuleManager\Tests\Performance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Mockery;
use UendelSilveira\PaymentModuleManager\Contracts\MercadoPagoClientInterface;
use UendelSilveira\PaymentModuleManager\Enums\PaymentGateway;
use UendelSilveira\PaymentModuleManager\Models\Transaction;
use UendelSilveira\PaymentModuleManager\Tests\TestCase;

/**
 * Performance Tests
 *
 * These tests ensure the system can handle expected load and meet performance requirements.
 * Thresholds are defined based on reasonable expectations for a payment processing system.
 */
class PaymentPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private const PERFORMANCE_THRESHOLD_MS = 1000; // 1 second max for payment processing

    private const BATCH_SIZE = 100;

    private const CACHE_THRESHOLD_MS = 50; // 50ms max for cached operations

    private const DB_QUERY_THRESHOLD_MS = 100; // 100ms max for simple queries

    protected function setUp(): void
    {
        parent::setUp();

        // Disable rate limiting for performance tests (except specific test)
        config(['payment.rate_limiting.enabled' => false]);

        // Mock MercadoPago client for all performance tests
        $this->instance(MercadoPagoClientInterface::class, Mockery::mock(MercadoPagoClientInterface::class, function ($mock) {
            $mock->shouldReceive('createPayment')
                ->andReturn((object) [
                    'id' => 'mp_perf_test_'.uniqid(),
                    'status' => 'approved',
                    'transaction_amount' => 100.00,
                    'description' => 'Performance test payment',
                    'payment_method_id' => 'pix',
                    'status_detail' => 'accredited',
                    'metadata' => (object) [],
                ]);

            $mock->shouldReceive('getPayment')
                ->andReturn((object) [
                    'id' => 'mp_perf_test',
                    'status' => 'approved',
                    'transaction_amount' => 100.00,
                    'description' => 'Performance test payment',
                    'payment_method_id' => 'pix',
                    'status_detail' => 'accredited',
                    'metadata' => (object) [],
                ]);

            $mock->shouldReceive('getPaymentMethods')
                ->andReturn([
                    ['id' => 'pix', 'name' => 'PIX'],
                    ['id' => 'credit_card', 'name' => 'Credit Card'],
                ]);
        }));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test: Single payment processing should complete within acceptable time
     *
     * @test
     */
    public function it_processes_single_payment_within_performance_threshold(): void
    {
        $payload = [
            'amount' => 100.00,
            'method' => PaymentGateway::MERCADOPAGO,
            'description' => 'Performance test payment',
            'payer_email' => 'test@example.com',
            'payment_method_id' => 'pix',
        ];

        $startTime = microtime(true);

        $response = $this->postJson(route('payment.process'), $payload);

        $elapsedTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

        $response->assertStatus(201);

        $this->assertLessThan(
            self::PERFORMANCE_THRESHOLD_MS,
            $elapsedTime,
            sprintf('Payment processing took %.2fms, expected less than %dms', $elapsedTime, self::PERFORMANCE_THRESHOLD_MS)
        );
    }

    /**
     * Test: Batch payment processing should maintain acceptable throughput
     *
     * @test
     */
    public function it_handles_batch_payment_processing_efficiently(): void
    {
        $batchSize = 50; // Reduced for test speed
        $payload = [
            'amount' => 100.00,
            'method' => PaymentGateway::MERCADOPAGO,
            'description' => 'Batch performance test',
            'payer_email' => 'test@example.com',
            'payment_method_id' => 'pix',
        ];

        $startTime = microtime(true);

        for ($i = 0; $i < $batchSize; $i++) {
            $response = $this->postJson(route('payment.process'), $payload);
            $response->assertStatus(201);
        }

        $totalTime = (microtime(true) - $startTime) * 1000;
        $avgTimePerPayment = $totalTime / $batchSize;

        // Average time per payment should be within threshold
        $this->assertLessThan(
            self::PERFORMANCE_THRESHOLD_MS,
            $avgTimePerPayment,
            sprintf('Average payment processing took %.2fms, expected less than %dms', $avgTimePerPayment, self::PERFORMANCE_THRESHOLD_MS)
        );

        // Total throughput should be reasonable (at least 10 payments/second)
        $paymentsPerSecond = ($batchSize / $totalTime) * 1000;
        $this->assertGreaterThan(
            10,
            $paymentsPerSecond,
            sprintf('Throughput was %.2f payments/second, expected at least 10', $paymentsPerSecond)
        );
    }

    /**
     * Test: Transaction queries should be fast with proper indexes
     *
     * @test
     */
    public function it_queries_transactions_efficiently_with_indexes(): void
    {
        // Create test data
        Transaction::factory()->count(1000)->create();

        // Test query by status (should use index)
        $startTime = microtime(true);
        $results = Transaction::where('status', 'approved')->get();
        $queryTime = (microtime(true) - $startTime) * 1000;

        $this->assertLessThan(
            self::DB_QUERY_THRESHOLD_MS,
            $queryTime,
            sprintf('Status query took %.2fms, expected less than %dms', $queryTime, self::DB_QUERY_THRESHOLD_MS)
        );

        // Test query by gateway + status (should use composite index)
        $startTime = microtime(true);
        $results = Transaction::where('gateway', PaymentGateway::MERCADOPAGO)
            ->where('status', 'approved')
            ->get();
        $queryTime = (microtime(true) - $startTime) * 1000;

        $this->assertLessThan(
            self::DB_QUERY_THRESHOLD_MS,
            $queryTime,
            sprintf('Composite index query took %.2fms, expected less than %dms', $queryTime, self::DB_QUERY_THRESHOLD_MS)
        );
    }

    /**
     * Test: Cache performance for settings
     *
     * @test
     */
    public function it_retrieves_cached_settings_efficiently(): void
    {
        $settingsKey = 'payment_settings:mercadopago';
        $testData = ['public_key' => 'test_key', 'access_token' => 'test_token'];

        // Warm up cache
        Cache::put($settingsKey, $testData, 3600);

        // Test cache retrieval performance
        $startTime = microtime(true);
        $result = Cache::get($settingsKey);
        $cacheTime = (microtime(true) - $startTime) * 1000;

        $this->assertLessThan(
            self::CACHE_THRESHOLD_MS,
            $cacheTime,
            sprintf('Cache retrieval took %.2fms, expected less than %dms', $cacheTime, self::CACHE_THRESHOLD_MS)
        );

        $this->assertEquals($testData, $result);
    }

    /**
     * Test: Health check endpoint performance
     *
     * @test
     */
    public function it_executes_health_check_efficiently(): void
    {
        $startTime = microtime(true);

        $response = $this->getJson(route('health.check'));

        $elapsedTime = (microtime(true) - $startTime) * 1000;

        $response->assertStatus(200);

        // Health check should be very fast (< 500ms even with DB + cache + API checks)
        $this->assertLessThan(
            500,
            $elapsedTime,
            sprintf('Health check took %.2fms, expected less than 500ms', $elapsedTime)
        );
    }

    /**
     * Test: Database connection pool performance
     *
     * @test
     */
    public function it_handles_concurrent_database_operations_efficiently(): void
    {
        $operations = 20;

        $startTime = microtime(true);

        for ($i = 0; $i < $operations; $i++) {
            DB::table('transactions')->insert([
                'gateway' => PaymentGateway::MERCADOPAGO,
                'amount' => 100.00,
                'currency' => 'BRL',
                'status' => 'pending',
                'description' => 'Concurrent test',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $totalTime = (microtime(true) - $startTime) * 1000;
        $avgTimePerInsert = $totalTime / $operations;

        // Each insert should be fast
        $this->assertLessThan(
            50,
            $avgTimePerInsert,
            sprintf('Average insert took %.2fms, expected less than 50ms', $avgTimePerInsert)
        );
    }

    /**
     * Test: Soft delete performance
     *
     * @test
     */
    public function it_soft_deletes_transactions_efficiently(): void
    {
        $transaction = Transaction::factory()->create();

        $startTime = microtime(true);
        $transaction->delete();
        $deleteTime = (microtime(true) - $startTime) * 1000;

        // Soft delete should be very fast
        $this->assertLessThan(
            100,
            $deleteTime,
            sprintf('Soft delete took %.2fms, expected less than 100ms', $deleteTime)
        );

        // Verify soft delete worked
        $this->assertSoftDeleted('transactions', ['id' => $transaction->id]);

        // Verify withTrashed query still works efficiently
        $startTime = microtime(true);
        $found = Transaction::withTrashed()->find($transaction->id);
        $queryTime = (microtime(true) - $startTime) * 1000;

        $this->assertNotNull($found);
        $this->assertLessThan(
            50,
            $queryTime,
            sprintf('Trashed query took %.2fms, expected less than 50ms', $queryTime)
        );
    }

    /**
     * Test: Memory usage stays within acceptable limits
     *
     * @test
     */
    public function it_maintains_acceptable_memory_usage_during_batch_operations(): void
    {
        $memoryBefore = memory_get_usage(true);

        // Process multiple payments
        for ($i = 0; $i < 50; $i++) {
            $payload = [
                'amount' => 100.00,
                'method' => PaymentGateway::MERCADOPAGO,
                'description' => 'Memory test payment',
                'payer_email' => 'test@example.com',
                'payment_method_id' => 'pix',
            ];

            $this->postJson(route('payment.process'), $payload);
        }

        $memoryAfter = memory_get_usage(true);
        $memoryUsed = ($memoryAfter - $memoryBefore) / 1024 / 1024; // Convert to MB

        // Memory increase should be reasonable (less than 50MB for 50 payments)
        $this->assertLessThan(
            50,
            $memoryUsed,
            sprintf('Memory usage increased by %.2fMB, expected less than 50MB', $memoryUsed)
        );
    }

    /**
     * Test: Rate limiting doesn't significantly impact performance for legitimate traffic
     *
     * @test
     */
    public function it_handles_rate_limiting_with_minimal_overhead(): void
    {
        // Enable rate limiting
        config(['payment.rate_limiting.enabled' => true]);
        config(['payment.rate_limiting.payment_query' => 60]);

        $transaction = Transaction::factory()->create([
            'external_id' => 'mp_rate_limit_test',
        ]);

        // Make 10 requests (well under limit)
        $times = [];

        for ($i = 0; $i < 10; $i++) {
            $startTime = microtime(true);
            $response = $this->getJson(route('payment.show', ['transaction' => $transaction->id]));
            $times[] = (microtime(true) - $startTime) * 1000;

            $response->assertStatus(200);
        }

        $avgTime = array_sum($times) / count($times);

        // Rate limiting overhead should be minimal (< 50ms per request)
        $this->assertLessThan(
            50,
            $avgTime,
            sprintf('Average request with rate limiting took %.2fms, expected less than 50ms', $avgTime)
        );
    }
}
