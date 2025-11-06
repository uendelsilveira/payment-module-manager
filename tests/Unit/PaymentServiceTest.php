<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 28/10/2025 20:57:39
*/

namespace UendelSilveira\PaymentModuleManager\Tests\Unit;

use Mockery;
use Mockery\MockInterface;
use UendelSilveira\PaymentModuleManager\Contracts\PaymentGatewayInterface;
use UendelSilveira\PaymentModuleManager\Contracts\TransactionRepositoryInterface;
use UendelSilveira\PaymentModuleManager\Models\Transaction;
use UendelSilveira\PaymentModuleManager\Services\GatewayManager;
use UendelSilveira\PaymentModuleManager\Services\PaymentService;
use UendelSilveira\PaymentModuleManager\Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    private MockInterface&GatewayManager $gatewayManager;

    private MockInterface&TransactionRepositoryInterface $transactionRepository;

    private PaymentService $paymentService;

    protected function setUp(): void
    {
        parent::setUp();
        /** @var MockInterface&GatewayManager $gatewayManager */
        $gatewayManager = Mockery::mock(GatewayManager::class);
        $this->gatewayManager = $gatewayManager;

        /** @var MockInterface&TransactionRepositoryInterface $transactionRepository */
        $transactionRepository = Mockery::mock(TransactionRepositoryInterface::class);
        $this->transactionRepository = $transactionRepository;

        $this->paymentService = new PaymentService($this->gatewayManager, $this->transactionRepository);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_be_instantiated_and_has_dependencies(): void
    {
        $this->assertInstanceOf(PaymentService::class, $this->paymentService);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function refund_method_correctly_handles_null_amount_for_full_refund(): void
    {
        // Arrange
        $transaction = new Transaction([
            'id' => 1,
            'gateway' => 'mercadopago',
            'external_id' => 'mp_payment_123',
            'amount' => 100.00,
            'status' => 'approved',
            'description' => 'Test Payment',
            'metadata' => ['payment_method_id' => 'pix'],
        ]);

        $expectedRefundResponse = [
            'id' => 'refund_123',
            'payment_id' => 'mp_payment_123',
            'amount' => 100.00,
            'status' => 'approved',
            'date_created' => '2025-11-06T19:30:00.000Z',
        ];

        $paymentGatewayMock = Mockery::mock(PaymentGatewayInterface::class);
        $paymentGatewayMock
            ->shouldReceive('refund')
            ->once()
            ->with('mp_payment_123', null)
            ->andReturn($expectedRefundResponse);

        $this->gatewayManager
            ->shouldReceive('create')
            ->once()
            ->with('mercadopago')
            ->andReturn($paymentGatewayMock);

        // Act
        $result = $this->paymentService->refundPayment($transaction, null);

        // Assert
        $this->assertEquals($expectedRefundResponse, $result);
        $this->assertEquals('refunded', $transaction->status);
        $this->assertIsArray($transaction->metadata);
        $this->assertArrayHasKey('refund', $transaction->metadata);
        $this->assertEquals($expectedRefundResponse, $transaction->metadata['refund']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function refund_method_correctly_handles_specific_amount_for_partial_refund(): void
    {
        // Arrange
        $transaction = new Transaction([
            'id' => 2,
            'gateway' => 'mercadopago',
            'external_id' => 'mp_payment_456',
            'amount' => 100.00,
            'status' => 'approved',
            'description' => 'Test Payment',
            'metadata' => ['payment_method_id' => 'credit_card'],
        ]);

        $partialAmount = 50.00;
        $expectedRefundResponse = [
            'id' => 'refund_456',
            'payment_id' => 'mp_payment_456',
            'amount' => $partialAmount,
            'status' => 'approved',
            'date_created' => '2025-11-06T19:30:00.000Z',
        ];

        $paymentGatewayMock = Mockery::mock(PaymentGatewayInterface::class);
        $paymentGatewayMock
            ->shouldReceive('refund')
            ->once()
            ->with('mp_payment_456', $partialAmount)
            ->andReturn($expectedRefundResponse);

        $this->gatewayManager
            ->shouldReceive('create')
            ->once()
            ->with('mercadopago')
            ->andReturn($paymentGatewayMock);

        // Act
        $result = $this->paymentService->refundPayment($transaction, $partialAmount);

        // Assert
        $this->assertEquals($expectedRefundResponse, $result);
        $this->assertEquals('refunded', $transaction->status);
        $this->assertIsArray($transaction->metadata);
        $this->assertArrayHasKey('refund', $transaction->metadata);
        $this->assertEquals($expectedRefundResponse, $transaction->metadata['refund']);
        $this->assertEquals($partialAmount, $result['amount']);
    }
}
