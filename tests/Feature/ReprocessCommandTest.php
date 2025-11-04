<?php

namespace UendelSilveira\PaymentModuleManager\Tests\Feature;

use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use UendelSilveira\PaymentModuleManager\Contracts\PaymentGatewayInterface;
use UendelSilveira\PaymentModuleManager\Models\Transaction;
use UendelSilveira\PaymentModuleManager\Services\GatewayManager;
use UendelSilveira\PaymentModuleManager\Tests\TestCase;

class ReprocessCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Desativa o uso de transações do RefreshDatabase para evitar conflito com o DB::transaction() do código.
     * O trait usará o método de truncar tabelas, que é mais lento mas evita o erro de "active transaction".
     */
    protected $connectionsToTransact = [];

    public function test_command_handles_reprocessing_failure_gracefully(): void
    {
        $transaction = Transaction::factory()->create([
            'status' => 'failed',
            'gateway' => 'mercadopago',
            'retries_count' => 0,
            'metadata' => ['payment_method_id' => 'pix'],
        ]);

        $mockStrategy = Mockery::mock(PaymentGatewayInterface::class);
        $errorMessage = 'Gateway Connection Error';
        $mockStrategy->shouldReceive('charge')
            ->once()
            ->with($transaction->amount, Mockery::type('array'))
            ->andThrow(new \Exception($errorMessage));

        $mockGatewayManager = Mockery::mock(GatewayManager::class);
        $mockGatewayManager->shouldReceive('create')->with('mercadopago')->andReturn($mockStrategy);

        // Substitui o GatewayManager ANTES de resolver o PaymentService
        $this->app->instance(GatewayManager::class, $mockGatewayManager);

        // Force a re-resolução do PaymentService com o novo GatewayManager
        $this->app->forgetInstance(\UendelSilveira\PaymentModuleManager\Services\PaymentService::class);

        $this->artisan('payment:reprocess-failed --force')
            ->expectsOutput(sprintf("❌ Failed to reprocess transaction #%d: %s", $transaction->id, $errorMessage))
            ->assertExitCode(Command::FAILURE);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'retries_count' => 1,
        ]);
    }

    public function test_command_succeeds_when_no_transactions_to_reprocess(): void
    {
        $this->artisan('payment:reprocess-failed')
            ->expectsOutput('✅ No failed transactions found matching the criteria')
            ->assertExitCode(Command::SUCCESS);
    }

    public function test_command_reprocesses_a_failed_transaction_successfully(): void
    {
        $transaction = Transaction::factory()->create([
            'status' => 'failed',
            'gateway' => 'mercadopago',
            'retries_count' => 0,
            'metadata' => ['payment_method_id' => 'pix'],
        ]);

        $mockStrategy = Mockery::mock(PaymentGatewayInterface::class);
        $mockStrategy->shouldReceive('charge')
            ->once()
            ->with($transaction->amount, Mockery::type('array'))
            ->andReturn([
                'id' => 'new_mp_id',
                'status' => 'approved',
                'payment_method_id' => 'pix',
                'transaction_amount' => $transaction->amount,
                'description' => $transaction->description,
                'status_detail' => 'accredited',
                'metadata' => [],
            ]);

        $mockGatewayManager = Mockery::mock(GatewayManager::class);
        $mockGatewayManager->shouldReceive('create')->with('mercadopago')->andReturn($mockStrategy);

        // Substitui o GatewayManager ANTES de resolver o PaymentService
        $this->app->instance(GatewayManager::class, $mockGatewayManager);

        // Force a re-resolução do PaymentService com o novo GatewayManager
        $this->app->forgetInstance(\UendelSilveira\PaymentModuleManager\Services\PaymentService::class);

        $this->artisan('payment:reprocess-failed --force')
            ->expectsOutput(sprintf("✅ Transaction #%d reprocessed - Status: approved", $transaction->id))
            ->assertExitCode(Command::SUCCESS);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => 'approved',
            'retries_count' => 1,
        ]);
    }
}
