<?php

namespace UendelSilveira\PaymentModuleManager\Tests\Feature\Jobs;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Mockery;
use UendelSilveira\PaymentModuleManager\Contracts\PaymentGatewayInterface;
use UendelSilveira\PaymentModuleManager\Contracts\TransactionRepositoryInterface;
use UendelSilveira\PaymentModuleManager\Enums\PaymentStatus;
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

        $gatewayMock = Mockery::mock(PaymentGatewayInterface::class);
        $gatewayMock->shouldReceive('handleWebhook')
            ->with($payload)
            ->andReturn([
                'transaction_id' => $externalId,
                'status' => PaymentStatus::APPROVED->value,
                'payment_method' => 'credit_card',
                'amount' => 100.00,
                'metadata' => [],
            ]);

        PaymentGateway::shouldReceive('gateway')->with($gatewayName)->andReturn($gatewayMock);

        $transaction = Transaction::factory()->create(['status' => 'pending', 'external_id' => $externalId]);

        $repositoryMock = Mockery::mock(TransactionRepositoryInterface::class);
        $repositoryMock->shouldReceive('findBy')->with('external_id', $externalId)->andReturn($transaction);
        $repositoryMock->shouldReceive('update')->with($transaction->id, Mockery::on(fn ($data): bool => $data['status'] === PaymentStatus::APPROVED->value));

        $processWebhookJob = new ProcessWebhookJob($gatewayName, $payload);
        $processWebhookJob->handle($repositoryMock);

        Event::assertDispatched(PaymentStatusChanged::class, fn ($event): bool => $event->transaction->id === $transaction->id
            && $event->oldStatus === 'pending'
            && $event->newStatus === PaymentStatus::APPROVED->value);
    }

    public function test_handles_transaction_not_found(): void
    {
        Log::shouldReceive('channel')->with('payment')->andReturnSelf();
        Log::shouldReceive('error')->once()->with('Transaction not found for webhook', Mockery::any());

        $payload = ['id' => '99999'];
        $gatewayName = 'mercadopago';

        $gatewayMock = Mockery::mock(PaymentGatewayInterface::class);
        $gatewayMock->shouldReceive('handleWebhook')
            ->with($payload)
            ->andReturn([
                'transaction_id' => '99999',
                'status' => PaymentStatus::APPROVED->value,
                'payment_method' => 'credit_card',
                'amount' => 100.00,
                'metadata' => [],
            ]);

        PaymentGateway::shouldReceive('gateway')->andReturn($gatewayMock);

        $repositoryMock = Mockery::mock(TransactionRepositoryInterface::class);
        $repositoryMock->shouldReceive('findBy')->with('external_id', Mockery::any())->andReturn(null);

        $processWebhookJob = new ProcessWebhookJob($gatewayName, $payload);
        $processWebhookJob->handle($repositoryMock);
    }

    public function test_idempotency_skips_processed_transactions(): void
    {
        Log::shouldReceive('channel')->with('payment')->andReturnSelf();
        Log::shouldReceive('info')->once()->with('Transaction already in final state, skipping webhook', Mockery::any());

        $payload = ['id' => '12345'];
        $gatewayName = 'mercadopago';

        $gatewayMock = Mockery::mock(PaymentGatewayInterface::class);
        $gatewayMock->shouldReceive('handleWebhook')
            ->andReturn([
                'transaction_id' => '12345',
                'status' => PaymentStatus::APPROVED->value,
                'payment_method' => 'credit_card',
                'amount' => 100.00,
                'metadata' => [],
            ]);

        PaymentGateway::shouldReceive('gateway')->andReturn($gatewayMock);

        // Alterado para um status final para ativar a lógica de idempotência
        $transaction = Transaction::factory()->create(['status' => PaymentStatus::REFUNDED->value, 'external_id' => '12345']);

        $repositoryMock = Mockery::mock(TransactionRepositoryInterface::class);
        $repositoryMock->shouldReceive('findBy')->with('external_id', '12345')->andReturn($transaction);
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

        $gatewayMock = Mockery::mock(PaymentGatewayInterface::class);
        $gatewayMock->shouldReceive('handleWebhook')
            ->andReturn([
                'transaction_id' => '12345',
                'status' => PaymentStatus::FAILED->value,
                'payment_method' => 'credit_card',
                'amount' => 100.00,
                'metadata' => [],
            ]);

        PaymentGateway::shouldReceive('gateway')->andReturn($gatewayMock);

        $transaction = Transaction::factory()->create(['status' => 'pending', 'external_id' => '12345']);

        $repositoryMock = Mockery::mock(TransactionRepositoryInterface::class);
        $repositoryMock->shouldReceive('findBy')->with('external_id', '12345')->andReturn($transaction);
        $repositoryMock->shouldReceive('update')->once();

        $processWebhookJob = new ProcessWebhookJob($gatewayName, $payload);
        $processWebhookJob->handle($repositoryMock);

        Event::assertDispatched(PaymentStatusChanged::class, fn ($event): bool => $event->transaction->id === $transaction->id
            && $event->oldStatus === 'pending'
            && $event->newStatus === PaymentStatus::FAILED->value);
    }
}
