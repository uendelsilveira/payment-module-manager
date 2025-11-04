<?php

namespace UendelSilveira\PaymentModuleManager\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use UendelSilveira\PaymentModuleManager\Contracts\MercadoPagoClientInterface;
use UendelSilveira\PaymentModuleManager\Enums\PaymentGateway;
use UendelSilveira\PaymentModuleManager\Tests\TestCase;

class IdempotencyTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_payment_with_same_idempotency_key_returns_same_transaction(): void
    {
        $this->instance(MercadoPagoClientInterface::class, Mockery::mock(MercadoPagoClientInterface::class, function ($mock) {
            $mock->shouldReceive('createPayment')->once()->andReturn((object) [
                'id' => 'mp_idempotency_test',
                'status' => 'approved',
                'transaction_amount' => 100.00,
                'description' => 'Idempotency test',
                'payment_method_id' => 'pix',
                'status_detail' => 'accredited',
                'metadata' => (object) [],
            ]);
        }));

        $idempotencyKey = 'test-idempotency-key-' . uniqid();

        $payload = [
            'amount' => 100.00,
            'method' => PaymentGateway::MERCADOPAGO,
            'description' => 'Idempotency test',
            'payer_email' => 'test@example.com',
            'payment_method_id' => 'pix',
        ];

        // First request
        $response1 = $this->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->postJson(route('payment.process'), $payload);

        $response1->assertStatus(201);
        $transactionId1 = $response1->json('data.id');

        // Second request with same idempotency key should return same transaction
        $response2 = $this->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->postJson(route('payment.process'), $payload);

        $response2->assertStatus(201);
        $this->assertEquals($transactionId1, $response2->json('data.id'), 'Should return same transaction ID');
        $response2->assertJsonPath('data.id', $transactionId1);

        // Verify only one transaction was created
        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_payment_without_idempotency_key_creates_new_transaction(): void
    {
        $this->instance(MercadoPagoClientInterface::class, Mockery::mock(MercadoPagoClientInterface::class, function ($mock) {
            $mock->shouldReceive('createPayment')->twice()->andReturn((object) [
                'id' => 'mp_test_' . uniqid(),
                'status' => 'approved',
                'transaction_amount' => 100.00,
                'description' => 'Test',
                'payment_method_id' => 'pix',
                'status_detail' => 'accredited',
                'metadata' => (object) [],
            ]);
        }));

        $payload = [
            'amount' => 100.00,
            'method' => PaymentGateway::MERCADOPAGO,
            'description' => 'Test without idempotency',
            'payer_email' => 'test@example.com',
            'payment_method_id' => 'pix',
        ];

        // First request without idempotency key
        $response1 = $this->postJson(route('payment.process'), $payload);
        $response1->assertStatus(201);

        // Second request without idempotency key should create new transaction
        $response2 = $this->postJson(route('payment.process'), $payload);
        $response2->assertStatus(201);

        // Verify two transactions were created
        $this->assertDatabaseCount('transactions', 2);
    }

    public function test_invalid_idempotency_key_format_returns_error(): void
    {
        $payload = [
            'amount' => 100.00,
            'method' => PaymentGateway::MERCADOPAGO,
            'description' => 'Invalid key test',
            'payer_email' => 'test@example.com',
            'payment_method_id' => 'pix',
        ];

        // Too short (less than 16 characters)
        $response = $this->withHeaders(['Idempotency-Key' => 'short'])
            ->postJson(route('payment.process'), $payload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid idempotency key format. Must be alphanumeric, 16-100 characters.',
            ]);
    }

    public function test_idempotency_uses_cache_for_subsequent_requests(): void
    {
        $this->instance(MercadoPagoClientInterface::class, Mockery::mock(MercadoPagoClientInterface::class, function ($mock) {
            $mock->shouldReceive('createPayment')->once()->andReturn((object) [
                'id' => 'mp_cache_test',
                'status' => 'approved',
                'transaction_amount' => 100.00,
                'description' => 'Cache test',
                'payment_method_id' => 'pix',
                'status_detail' => 'accredited',
                'metadata' => (object) [],
            ]);
        }));

        $idempotencyKey = 'test-cache-key-' . uniqid();

        $payload = [
            'amount' => 100.00,
            'method' => PaymentGateway::MERCADOPAGO,
            'description' => 'Cache test',
            'payer_email' => 'test@example.com',
            'payment_method_id' => 'pix',
        ];

        // First request
        $response1 = $this->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->postJson(route('payment.process'), $payload);

        $response1->assertStatus(201);

        // Verify cache was set
        $cacheKey = "idempotency:{$idempotencyKey}";
        $this->assertTrue(Cache::has($cacheKey));

        // Second request should use cache (mock expects only 1 call to createPayment)
        $response2 = $this->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->postJson(route('payment.process'), $payload);

        $response2->assertStatus(201);
    }

    public function test_different_idempotency_keys_create_different_transactions(): void
    {
        $this->instance(MercadoPagoClientInterface::class, Mockery::mock(MercadoPagoClientInterface::class, function ($mock) {
            $mock->shouldReceive('createPayment')->twice()->andReturn((object) [
                'id' => 'mp_diff_test_' . uniqid(),
                'status' => 'approved',
                'transaction_amount' => 100.00,
                'description' => 'Different keys test',
                'payment_method_id' => 'pix',
                'status_detail' => 'accredited',
                'metadata' => (object) [],
            ]);
        }));

        $payload = [
            'amount' => 100.00,
            'method' => PaymentGateway::MERCADOPAGO,
            'description' => 'Different keys test',
            'payer_email' => 'test@example.com',
            'payment_method_id' => 'pix',
        ];

        // First request with key 1
        $response1 = $this->withHeaders(['Idempotency-Key' => 'test-key-one-' . uniqid()])
            ->postJson(route('payment.process'), $payload);

        $response1->assertStatus(201);
        $transactionId1 = $response1->json('data.id');

        // Second request with different key
        $response2 = $this->withHeaders(['Idempotency-Key' => 'test-key-two-' . uniqid()])
            ->postJson(route('payment.process'), $payload);

        $response2->assertStatus(201);
        $transactionId2 = $response2->json('data.id');

        // Verify different transactions were created
        $this->assertNotEquals($transactionId1, $transactionId2);
        $this->assertDatabaseCount('transactions', 2);
    }
}
