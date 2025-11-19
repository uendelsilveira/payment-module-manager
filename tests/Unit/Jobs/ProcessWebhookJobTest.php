<?php

namespace Tests\Unit\Jobs;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Mockery;
use UendelSilveira\PaymentModuleManager\Contracts\PaymentGatewayInterface;
use UendelSilveira\PaymentModuleManager\Contracts\TransactionRepositoryInterface;
use UendelSilveira\PaymentModuleManager\Events\PaymentStatusChanged;
use UendelSilveira\PaymentModuleManager\Facades\PaymentGateway;
use UendelSilveira\PaymentModuleManager\Jobs\ProcessWebhookJob;
use UendelSilveira\PaymentModuleManager\Models\Transaction;
use UendelSilveira\PaymentModuleManager\Tests\TestCase;

class ProcessWebhookJobTest extends TestCase
{
    public function test_processes_successful_payment_webhook(): void
    {
        Event::fake();
        Log::shouldReceive('channel')->with('payment')->andReturnSelf();
        Log::shouldReceive('info');
        Log::shouldReceive('error')->never();

        $payload = ['id' => '12345', 'status' => 'approved'];
        $gatewayName = 'mercadopago';
        $externalId = '12345';

        // Mock Gateway
        $gatewayMock = Mockery::mock(PaymentGatewayInterface::class);
        $gatewayMock->shouldReceive('processWebhook')
            ->with($payload)
            ->andReturn([
                'transaction_id' => $externalId,
                'status' => 'completed',
                'payment_method' => 'credit_card',
                'amount' => 100.00,
                'metadata' => [],
            ]);

        PaymentGateway::shouldReceive('gateway')
            ->with($gatewayName)
            ->andReturn($gatewayMock);

        // Real Transaction Object
        $transaction = new Transaction;
        $transaction->id = 1;
        $transaction->status = 'pending';
        $transaction->metadata = [];
        $transaction->external_id = $externalId;

        $repositoryMock = Mockery::mock(TransactionRepositoryInterface::class);
        $repositoryMock->shouldReceive('findBy')
            ->with('external_id', $externalId)
            ->andReturn($transaction);

        $repositoryMock->shouldReceive('update')
            ->with(1, Mockery::on(fn ($data): bool => $data['status'] === 'completed'));

        $processWebhookJob = new ProcessWebhookJob($gatewayName, $payload);
        $processWebhookJob->handle($repositoryMock);

        Event::assertDispatched(PaymentStatusChanged::class, fn ($event): bool => $event->transaction->id === $transaction->id
            && $event->oldStatus === 'pending'
            && $event->newStatus === 'completed');
    }

    public function test_handles_transaction_not_found(): void
    {
        Log::shouldReceive('channel')->with('payment')->andReturnSelf();
        Log::shouldReceive('error')->once()->with('Transaction not found for webhook', Mockery::any());

        $payload = ['id' => '99999'];
        $gatewayName = 'mercadopago';

        // Mock Gateway
        $gatewayMock = Mockery::mock(PaymentGatewayInterface::class);
        $gatewayMock->shouldReceive('processWebhook')
            ->andReturn([
                'transaction_id' => '99999',
                'status' => 'completed',
                'payment_method' => 'credit_card',
                'amount' => 100.00,
                'metadata' => [],
            ]);

        PaymentGateway::shouldReceive('gateway')->andReturn($gatewayMock);

        // Mock Repository returning null
        $repositoryMock = Mockery::mock(TransactionRepositoryInterface::class);
        $repositoryMock->shouldReceive('findBy')->andReturn(null);

        $processWebhookJob = new ProcessWebhookJob($gatewayName, $payload);
        $processWebhookJob->handle($repositoryMock);
    }

    public function test_idempotency_skips_processed_transactions(): void
    {
        Log::shouldReceive('channel')->with('payment')->andReturnSelf();
        Log::shouldReceive('info')->once()->with('Transaction already in final state, skipping webhook', Mockery::any());

        $payload = ['id' => '12345'];
        $gatewayName = 'mercadopago';

        // Mock Gateway
        $gatewayMock = Mockery::mock(PaymentGatewayInterface::class);
        $gatewayMock->shouldReceive('processWebhook')
            ->andReturn([
                'transaction_id' => '12345',
                'status' => 'completed', // New status
                'payment_method' => 'credit_card',
                'amount' => 100.00,
                'metadata' => [],
            ]);

        PaymentGateway::shouldReceive('gateway')->andReturn($gatewayMock);

        // Real Transaction Object already completed
        $transaction = new Transaction;
        $transaction->id = 1;
        $transaction->status = 'completed';
        $transaction->external_id = '12345';

        $repositoryMock = Mockery::mock(TransactionRepositoryInterface::class);
        $repositoryMock->shouldReceive('findBy')->andReturn($transaction);

        // Ensure update is NEVER called
        $repositoryMock->shouldReceive('update')->never();

        $processWebhookJob = new ProcessWebhookJob($gatewayName, $payload);
        $processWebhookJob->handle($repositoryMock);
    }

    public function test_dispatches_payment_status_changed_event_on_failure(): void
    {
        Event::fake();
        Log::shouldReceive('channel')->with('payment')->andReturnSelf();
        Log::shouldReceive('info');

        $payload = ['id' => '12345', 'status' => 'rejected'];
        $gatewayName = 'mercadopago';

        // Mock Gateway
        $gatewayMock = Mockery::mock(PaymentGatewayInterface::class);
        $gatewayMock->shouldReceive('processWebhook')
            ->andReturn([
                'transaction_id' => '12345',
                'status' => 'failed',
                'payment_method' => 'credit_card',
                'amount' => 100.00,
                'metadata' => [],
            ]);

        PaymentGateway::shouldReceive('gateway')->andReturn($gatewayMock);

        // Real Transaction Object
        $transaction = new Transaction;
        $transaction->id = 1;
        $transaction->status = 'pending';
        $transaction->metadata = [];
        $transaction->external_id = '12345';

        $repositoryMock = Mockery::mock(TransactionRepositoryInterface::class);
        $repositoryMock->shouldReceive('findBy')->andReturn($transaction);
        $repositoryMock->shouldReceive('update')->once();

        $processWebhookJob = new ProcessWebhookJob($gatewayName, $payload);
        $processWebhookJob->handle($repositoryMock);

        Event::assertDispatched(PaymentStatusChanged::class, fn ($event): bool => $event->transaction->id === $transaction->id
            && $event->oldStatus === 'pending'
            && $event->newStatus === 'failed');
    }
}
