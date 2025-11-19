<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use UendelSilveira\PaymentModuleManager\Events\PaymentStatusChanged;
use UendelSilveira\PaymentModuleManager\Jobs\ProcessWebhookJob;
use UendelSilveira\PaymentModuleManager\Models\Transaction;
use UendelSilveira\PaymentModuleManager\Tests\TestCase;

class WebhookProcessingTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_job_updates_transaction_and_dispatches_event(): void
    {
        // We need to use the real queue or simulate the job execution
        // For this test, we'll manually execute the job to verify the side effects
        // as integration testing the queue worker is complex in this setup.

        Event::fake();

        // Create a pending transaction
        $transaction = Transaction::create([
            'gateway' => 'stripe',
            'amount' => 10.00,
            'status' => 'pending',
            'external_id' => 'pi_12345', // Matches the mock payload
            'description' => 'Test Transaction',
        ]);

        $payload = [
            'id' => 'evt_12345',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_12345',
                    'amount' => 1000,
                    'currency' => 'usd',
                    'status' => 'succeeded',
                ],
            ],
        ];

        // Mock the gateway response via Facade or by mocking the manager in the container
        // Since ProcessWebhookJob uses the facade, we need to mock the underlying service

        $mockGateway = \Mockery::mock(\UendelSilveira\PaymentModuleManager\Contracts\PaymentGatewayInterface::class);
        $mockGateway->shouldReceive('processWebhook')
            ->with($payload)
            ->andReturn([
                'transaction_id' => 'pi_12345',
                'status' => 'approved',
                'payment_method' => 'credit_card',
                'amount' => 10.00,
                'metadata' => ['foo' => 'bar'],
            ]);

        \UendelSilveira\PaymentModuleManager\Facades\PaymentGateway::shouldReceive('gateway')
            ->with('stripe')
            ->andReturn($mockGateway);

        // Execute the job
        $processWebhookJob = new ProcessWebhookJob('stripe', $payload);
        $processWebhookJob->handle(app(\UendelSilveira\PaymentModuleManager\Contracts\TransactionRepositoryInterface::class));

        // Verify Transaction Update
        $transaction->refresh();
        $this->assertEquals('approved', $transaction->status);
        $this->assertArrayHasKey('webhook_processed_at', $transaction->metadata);

        // Verify Event Dispatch
        Event::assertDispatched(PaymentStatusChanged::class, fn ($event): bool => $event->transaction->id === $transaction->id
            && $event->oldStatus === 'pending'
            && $event->newStatus === 'approved');
    }
}
