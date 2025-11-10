<?php

namespace UendelSilveira\PaymentModuleManager\Tests\Unit;

use Mockery;
use Mockery\MockInterface;
use UendelSilveira\PaymentModuleManager\Contracts\GatewayInterface;
use UendelSilveira\PaymentModuleManager\Contracts\TransactionRepositoryInterface;
use UendelSilveira\PaymentModuleManager\Models\Transaction;
use UendelSilveira\PaymentModuleManager\Services\GatewayManager;
use UendelSilveira\PaymentModuleManager\Services\PaymentService;
use UendelSilveira\PaymentModuleManager\Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    private \Mockery\MockInterface&\UendelSilveira\PaymentModuleManager\Services\GatewayManager $gatewayManager;

    private \Mockery\MockInterface&\UendelSilveira\PaymentModuleManager\Contracts\TransactionRepositoryInterface $transactionRepository;

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

    public function test_refund_method_correctly_handles_null_amount_for_full_refund(): void
    {
        // Arrange
        $transaction = new Transaction([
            'id' => 1,
            'gateway' => 'mercadopago',
            'external_id' => 'mp_payment_123',
            'amount' => 100.00,
            'status' => 'approved',
        ]);

        $expectedRefundResponse = ['id' => 'refund_123', 'status' => 'approved'];

        // Mock da nossa interface unificada GatewayInterface
        $gatewayMock = Mockery::mock(GatewayInterface::class);
        $gatewayMock->shouldReceive('refund')->once()->with('mp_payment_123', null)->andReturn($expectedRefundResponse);

        $this->gatewayManager->shouldReceive('create')->once()->with('mercadopago')->andReturn($gatewayMock);

        // Act
        $result = $this->paymentService->refundPayment($transaction);

        // Assert
        $this->assertEquals($expectedRefundResponse, $result);
        $this->assertEquals('refunded', $transaction->status);
    }

    public function test_refund_method_correctly_handles_specific_amount_for_partial_refund(): void
    {
        // Arrange
        $transaction = new Transaction([
            'id' => 2,
            'gateway' => 'mercadopago',
            'external_id' => 'mp_payment_456',
            'amount' => 100.00,
            'status' => 'approved',
        ]);
        $partialAmount = 50.00;
        $expectedRefundResponse = ['id' => 'refund_456', 'status' => 'approved', 'amount' => $partialAmount];

        $gatewayMock = Mockery::mock(GatewayInterface::class);
        $gatewayMock->shouldReceive('refund')->once()->with('mp_payment_456', $partialAmount)->andReturn($expectedRefundResponse);

        $this->gatewayManager->shouldReceive('create')->once()->with('mercadopago')->andReturn($gatewayMock);

        // Act
        $result = $this->paymentService->refundPayment($transaction, $partialAmount);

        // Assert
        $this->assertEquals($expectedRefundResponse, $result);
        $this->assertEquals('refunded', $transaction->status);
    }

    public function test_cancel_method_successfully_cancels_payment(): void
    {
        // Arrange
        $transaction = new Transaction(['id' => 3, 'gateway' => 'mercadopago', 'external_id' => 'mp_payment_789', 'amount' => 100.00, 'status' => 'in_process']);
        $expectedCancelResponse = ['id' => 'mp_payment_789', 'status' => 'cancelled'];

        $gatewayMock = Mockery::mock(GatewayInterface::class);
        $gatewayMock->shouldReceive('cancel')->once()->with('mp_payment_789')->andReturn($expectedCancelResponse);

        $this->gatewayManager->shouldReceive('create')->once()->with('mercadopago')->andReturn($gatewayMock);

        // Act
        $result = $this->paymentService->cancelPayment($transaction);

        // Assert
        $this->assertEquals($expectedCancelResponse, $result);
        $this->assertEquals('cancelled', $transaction->status);
    }

    public function test_cancel_method_throws_exception_for_invalid_status(): void
    {
        // Arrange
        $transaction = new Transaction(['amount' => 100.00, 'external_id' => 'mp_payment_999', 'status' => 'approved']);

        // Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Apenas pagamentos pendentes ou em processamento podem ser cancelados. Status atual: approved');

        // Act
        $this->paymentService->cancelPayment($transaction);
    }
}
