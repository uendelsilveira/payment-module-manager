<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 04/11/2025 16:09:38
*/

namespace UendelSilveira\PaymentModuleManager\Tests\Unit;

use UendelSilveira\PaymentModuleManager\Models\Transaction;
use UendelSilveira\PaymentModuleManager\Support\LogContext;
use UendelSilveira\PaymentModuleManager\Tests\TestCase;

class LogContextTest extends TestCase
{
    public function test_can_create_log_context(): void
    {
        $context = LogContext::create();

        $this->assertInstanceOf(LogContext::class, $context);
        $this->assertIsArray($context->toArray());
    }

    public function test_can_add_correlation_id(): void
    {
        $context = LogContext::create()->withCorrelationId();

        $this->assertArrayHasKey('correlation_id', $context->toArray());
        $this->assertNotEmpty($context->toArray()['correlation_id']);
    }

    public function test_can_add_custom_correlation_id(): void
    {
        $customId = 'custom-correlation-id';
        $context = LogContext::create()->withCorrelationId($customId);

        $this->assertEquals($customId, $context->toArray()['correlation_id']);
    }

    public function test_can_add_gateway(): void
    {
        $context = LogContext::create()->withGateway('mercadopago');

        $this->assertEquals('mercadopago', $context->toArray()['gateway']);
    }

    public function test_can_add_amount(): void
    {
        $context = LogContext::create()->withAmount(100.50);

        $this->assertEquals(100.50, $context->toArray()['amount']);
    }

    public function test_can_add_payment_method(): void
    {
        $context = LogContext::create()->withPaymentMethod('pix');

        $this->assertEquals('pix', $context->toArray()['payment_method']);
    }

    public function test_can_add_transaction(): void
    {
        $transaction = Transaction::factory()->create([
            'external_id' => 'ext-123',
            'gateway' => 'mercadopago',
            'status' => 'approved',
            'amount' => 100.00,
        ]);

        $context = LogContext::create()->withTransaction($transaction);
        $data = $context->toArray();

        $this->assertArrayHasKey('transaction', $data);
        $this->assertNotNull($data['transaction']['id']);
        $this->assertEquals('ext-123', $data['transaction']['external_id']);
        $this->assertEquals('mercadopago', $data['transaction']['gateway']);
        $this->assertEquals('approved', $data['transaction']['status']);
        $this->assertEquals(100.00, $data['transaction']['amount']);
    }

    public function test_can_add_transaction_id(): void
    {
        $context = LogContext::create()->withTransactionId(123);

        $this->assertEquals(123, $context->toArray()['transaction_id']);
    }

    public function test_can_add_external_id(): void
    {
        $context = LogContext::create()->withExternalId('ext-456');

        $this->assertEquals('ext-456', $context->toArray()['external_id']);
    }

    public function test_can_add_webhook(): void
    {
        $webhookData = [
            'type' => 'payment',
            'action' => 'payment.updated',
            'data' => ['id' => '123'],
        ];

        $context = LogContext::create()->withWebhook($webhookData);
        $data = $context->toArray();

        $this->assertArrayHasKey('webhook', $data);
        $this->assertEquals('payment', $data['webhook']['type']);
        $this->assertEquals('payment.updated', $data['webhook']['action']);
        $this->assertEquals('123', $data['webhook']['data_id']);
    }

    public function test_can_add_request_id(): void
    {
        $context = LogContext::create()->withRequestId();

        $this->assertArrayHasKey('request_id', $context->toArray());
        $this->assertNotEmpty($context->toArray()['request_id']);
    }

    public function test_can_add_custom_request_id(): void
    {
        $customRequestId = 'custom-request-id';
        $context = LogContext::create()->withRequestId($customRequestId);

        $this->assertEquals($customRequestId, $context->toArray()['request_id']);
    }

    public function test_can_add_duration(): void
    {
        $startTime = microtime(true);
        usleep(10000); // Sleep 10ms
        $context = LogContext::create()->withDuration($startTime);

        $this->assertArrayHasKey('duration_ms', $context->toArray());
        $this->assertGreaterThan(0, $context->toArray()['duration_ms']);
    }

    public function test_can_add_error(): void
    {
        $exception = new \Exception('Test error', 500);
        $context = LogContext::create()->withError($exception);
        $data = $context->toArray();

        $this->assertArrayHasKey('error', $data);
        $this->assertEquals(\Exception::class, $data['error']['class']);
        $this->assertEquals('Test error', $data['error']['message']);
        $this->assertEquals(500, $data['error']['code']);
        $this->assertArrayHasKey('file', $data['error']);
        $this->assertArrayHasKey('line', $data['error']);
    }

    public function test_can_add_retry(): void
    {
        $context = LogContext::create()->withRetry(2, 3);
        $data = $context->toArray();

        $this->assertArrayHasKey('retry', $data);
        $this->assertEquals(2, $data['retry']['attempt']);
        $this->assertEquals(3, $data['retry']['max_attempts']);
    }

    public function test_can_add_custom_field(): void
    {
        $context = LogContext::create()->with('custom_field', 'custom_value');

        $this->assertEquals('custom_value', $context->toArray()['custom_field']);
    }

    public function test_can_add_many_custom_fields(): void
    {
        $context = LogContext::create()->withMany([
            'field1' => 'value1',
            'field2' => 'value2',
            'field3' => 'value3',
        ]);

        $data = $context->toArray();
        $this->assertEquals('value1', $data['field1']);
        $this->assertEquals('value2', $data['field2']);
        $this->assertEquals('value3', $data['field3']);
    }

    public function test_can_mask_sensitive_data(): void
    {
        $context = LogContext::create()->withMany([
            'token' => 'secret-token',
            'access_token' => 'secret-access',
            'password' => 'secret-password',
            'public_field' => 'visible',
        ])->maskSensitiveData();

        $data = $context->toArray();
        $this->assertEquals('***MASKED***', $data['token']);
        $this->assertEquals('***MASKED***', $data['access_token']);
        $this->assertEquals('***MASKED***', $data['password']);
        $this->assertEquals('visible', $data['public_field']);
    }

    public function test_can_mask_nested_sensitive_data(): void
    {
        $context = LogContext::create()->withMany([
            'user' => [
                'name' => 'John Doe',
                'password' => 'secret',
                'token' => 'secret-token',
            ],
            'payment' => [
                'amount' => 100,
                'cvv' => '123',
            ],
        ])->maskSensitiveData();

        $data = $context->toArray();
        $this->assertEquals('John Doe', $data['user']['name']);
        $this->assertEquals('***MASKED***', $data['user']['password']);
        $this->assertEquals('***MASKED***', $data['user']['token']);
        $this->assertEquals(100, $data['payment']['amount']);
        $this->assertEquals('***MASKED***', $data['payment']['cvv']);
    }

    public function test_context_is_chainable(): void
    {
        $context = LogContext::create()
            ->withCorrelationId()
            ->withGateway('mercadopago')
            ->withAmount(100.00)
            ->withPaymentMethod('pix')
            ->withRequestId();

        $data = $context->toArray();
        $this->assertArrayHasKey('correlation_id', $data);
        $this->assertArrayHasKey('gateway', $data);
        $this->assertArrayHasKey('amount', $data);
        $this->assertArrayHasKey('payment_method', $data);
        $this->assertArrayHasKey('request_id', $data);
    }
}
