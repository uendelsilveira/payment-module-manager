<?php

namespace UendelSilveira\PaymentModuleManager\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery;
use UendelSilveira\PaymentModuleManager\Contracts\PaymentGatewayInterface;
use UendelSilveira\PaymentModuleManager\Contracts\TransactionRepositoryInterface;
use UendelSilveira\PaymentModuleManager\Enums\PaymentStatus;
use UendelSilveira\PaymentModuleManager\Events\PaymentStatusChanged;
use UendelSilveira\PaymentModuleManager\Facades\PaymentGateway;
use UendelSilveira\PaymentModuleManager\Jobs\ProcessWebhookJob;
use UendelSilveira\PaymentModuleManager\Models\Transaction;
use UendelSilveira\PaymentModuleManager\Tests\TestCase;

class WebhookProcessingTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_job_updates_transaction_and_dispatches_event(): void
    {
        Event::fake();

        $transaction = Transaction::factory()->create([
            'gateway' => 'stripe',
            'status' => PaymentStatus::PENDING,
            'external_id' => 'pi_12345',
        ]);

        $payload = [
            'id' => 'evt_12345',
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'pi_12345', 'status' => 'succeeded']],
        ];

        $mockGateway = Mockery::mock(PaymentGatewayInterface::class);
        $mockGateway->shouldReceive('handleWebhook')
            ->with($payload)
            ->andReturn([
                'transaction_id' => 'pi_12345',
                'status' => PaymentStatus::APPROVED->value,
                'payment_method' => 'credit_card',
                'amount' => 10.00,
                'metadata' => ['foo' => 'bar'],
            ]);

        PaymentGateway::shouldReceive('gateway')->with('stripe')->andReturn($mockGateway);

        $processWebhookJob = new ProcessWebhookJob('stripe', $payload);
        $processWebhookJob->handle(app(TransactionRepositoryInterface::class));

        $transaction->refresh();
        $this->assertEquals(PaymentStatus::APPROVED->value, $transaction->status);
        $this->assertArrayHasKey('webhook_processed_at', $transaction->metadata);

        Event::assertDispatched(PaymentStatusChanged::class, function (PaymentStatusChanged $event) use ($transaction) {
            return $event->transaction->id === $transaction->id &&
                   $event->oldStatus === PaymentStatus::PENDING->value &&
                   $event->newStatus === PaymentStatus::APPROVED->value;
        });
    }
}
