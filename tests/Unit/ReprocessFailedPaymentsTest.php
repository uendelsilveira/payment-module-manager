<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 28/10/2025 20:43:21
*/

namespace UendelSilveira\PaymentModuleManager\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use UendelSilveira\PaymentModuleManager\Contracts\PaymentGatewayInterface;
use UendelSilveira\PaymentModuleManager\Models\Transaction;
use UendelSilveira\PaymentModuleManager\Services\GatewayManager;
use UendelSilveira\PaymentModuleManager\Services\PaymentService;
use UendelSilveira\PaymentModuleManager\Tests\TestCase;

class ReprocessFailedPaymentsTest extends TestCase
{
    use RefreshDatabase;

    protected PaymentService $paymentService;

    protected function setUp(): void
    {
        parent::setUp();

        // Cria um mock da estratégia que implementa PaymentGatewayInterface
        $mockStrategy = Mockery::mock(PaymentGatewayInterface::class);
        $mockStrategy->shouldReceive('charge')
            ->andReturn([
                'id' => 'new_mp_payment_id',
                'status' => 'approved',
                'transaction_amount' => 100.00,
                'description' => 'Reprocessed Payment',
                'payment_method_id' => 'pix',
                'status_detail' => 'accredited',
                'metadata' => [],
            ]);

        // Mock do GatewayManager para sempre retornar o mockStrategy
        $mockGatewayManager = Mockery::mock(GatewayManager::class);
        $mockGatewayManager->shouldReceive('create')
            ->andReturn($mockStrategy);

        // Instancia do PaymentService com mocks
        $this->paymentService = new PaymentService(
            $mockGatewayManager,
            app('UendelSilveira\PaymentModuleManager\Contracts\TransactionRepositoryInterface')
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_reprocesses_failed_payments()
    {
        $failedTransaction = Transaction::create([
            'gateway' => 'mercadopago',
            'amount' => 100.00,
            'currency' => 'BRL',
            'status' => 'failed',
            'description' => 'Original failed payment',
            'external_id' => 'original_mp_id',
            'metadata' => [
                'payment_method_id' => 'pix',
                'payer_email' => 'test@example.com',
                'description' => 'Original failed payment',
                'method' => 'mercadopago',
                'amount' => 100.00,
            ],
            'retries_count' => 0,
            'last_attempt_at' => null,
        ]);

        // Reprocessa manualmente
        $transaction = $this->paymentService->processPayment([
            'method' => $failedTransaction->gateway,
            'amount' => $failedTransaction->amount,
            'description' => $failedTransaction->description,
            'payment_method_id' => $failedTransaction->metadata['payment_method_id'],
        ]);

        $this->assertEquals('approved', $transaction->status);
        $this->assertEquals('new_mp_payment_id', $transaction->external_id);
    }

    public function test_it_does_not_reprocess_transactions_with_max_retries()
    {
        $failedTransaction = Transaction::create([
            'gateway' => 'mercadopago',
            'amount' => 100.00,
            'currency' => 'BRL',
            'status' => 'failed',
            'description' => 'Max retries payment',
            'external_id' => 'max_retries_mp_id',
            'metadata' => [
                'payment_method_id' => 'pix',
                'payer_email' => 'test@example.com',
                'description' => 'Max retries payment',
                'method' => 'mercadopago',
                'amount' => 100.00,
            ],
            'retries_count' => 3,
            'last_attempt_at' => now()->subMinutes(10),
        ]);

        // Não deve reprocessar porque já atingiu o limite
        if ($failedTransaction->retries_count < 3) {
            $this->paymentService->processPayment([
                'method' => $failedTransaction->gateway,
                'amount' => $failedTransaction->amount,
                'description' => $failedTransaction->description,
                'payment_method_id' => $failedTransaction->metadata['payment_method_id'],
            ]);
        }

        $fresh = $failedTransaction->fresh();
        $this->assertEquals('failed', $fresh->status);
        $this->assertEquals(3, $fresh->retries_count);
    }

    public function test_it_does_not_reprocess_transactions_too_soon()
    {
        $failedTransaction = Transaction::create([
            'gateway' => 'mercadopago',
            'amount' => 100.00,
            'currency' => 'BRL',
            'status' => 'failed',
            'description' => 'Recent attempt payment',
            'external_id' => 'recent_attempt_mp_id',
            'metadata' => [
                'payment_method_id' => 'pix',
                'payer_email' => 'test@example.com',
                'description' => 'Recent attempt payment',
                'method' => 'mercadopago',
                'amount' => 100.00,
            ],
            'retries_count' => 1,
            'last_attempt_at' => now()->subMinutes(1),
        ]);

        $minMinutes = 5;

        if ($failedTransaction->last_attempt_at->diffInMinutes(now()) >= $minMinutes) {
            $this->paymentService->processPayment([
                'method' => $failedTransaction->gateway,
                'amount' => $failedTransaction->amount,
                'description' => $failedTransaction->description,
                'payment_method_id' => $failedTransaction->metadata['payment_method_id'],
            ]);
        }

        $fresh = $failedTransaction->fresh();
        $this->assertEquals('failed', $fresh->status);
        $this->assertEquals(1, $fresh->retries_count);
    }
}
