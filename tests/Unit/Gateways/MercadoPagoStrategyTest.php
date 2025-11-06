<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 06/11/2025 20:22:45
*/

namespace UendelSilveira\PaymentModuleManager\Tests\Unit\Gateways;

use Exception;
use Mockery;
use Mockery\MockInterface;
use UendelSilveira\PaymentModuleManager\Contracts\MercadoPagoClientInterface;
use UendelSilveira\PaymentModuleManager\Gateways\MercadoPagoStrategy;
use UendelSilveira\PaymentModuleManager\Tests\TestCase;

class MercadoPagoStrategyTest extends TestCase
{
    private MockInterface&MercadoPagoClientInterface $mpClientMock;

    private MercadoPagoStrategy $mercadoPagoStrategy;

    protected function setUp(): void
    {
        parent::setUp();
        /** @var MockInterface&MercadoPagoClientInterface $mpClientMock */
        $mpClientMock = Mockery::mock(MercadoPagoClientInterface::class);
        $this->mpClientMock = $mpClientMock;
        $this->mercadoPagoStrategy = new MercadoPagoStrategy($this->mpClientMock);
    }

    public function test_get_payment_methods_successfully(): void
    {
        // Arrange
        $expectedPaymentMethods = [
            (object) ['id' => 'pix', 'name' => 'Pix'],
            (object) ['id' => 'credit_card', 'name' => 'Credit Card'],
        ];

        $this->mpClientMock
            ->shouldReceive('getPaymentMethods')
            ->once()
            ->andReturn($expectedPaymentMethods);

        // Act
        $paymentMethods = $this->mercadoPagoStrategy->getPaymentMethods();

        // Assert
        $this->assertEquals($expectedPaymentMethods, $paymentMethods);
    }

    public function test_get_payment_methods_throws_exception(): void
    {
        // Arrange
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Erro ao buscar métodos de pagamento: Test Exception');

        $this->mpClientMock
            ->shouldReceive('getPaymentMethods')
            ->once()
            ->andThrow(new Exception('Test Exception'));

        // Act
        $this->mercadoPagoStrategy->getPaymentMethods();
    }

    public function test_refund_full_amount_successfully(): void
    {
        // Arrange
        $paymentId = '123456789';
        $expectedRefund = (object) [
            'id' => 'refund_123',
            'payment_id' => $paymentId,
            'amount' => 100.00,
            'status' => 'approved',
            'date_created' => '2025-11-06T19:30:00.000Z',
        ];

        $this->mpClientMock
            ->shouldReceive('createRefund')
            ->once()
            ->with($paymentId, [])
            ->andReturn($expectedRefund);

        // Act
        $result = $this->mercadoPagoStrategy->refund($paymentId);

        // Assert
        $this->assertEquals('refund_123', $result['id']);
        $this->assertEquals($paymentId, $result['payment_id']);
        $this->assertEquals(100.00, $result['amount']);
        $this->assertEquals('approved', $result['status']);
    }

    public function test_refund_partial_amount_successfully(): void
    {
        // Arrange
        $paymentId = '123456789';
        $partialAmount = 50.00;
        $expectedRefund = (object) [
            'id' => 'refund_456',
            'payment_id' => $paymentId,
            'amount' => $partialAmount,
            'status' => 'approved',
            'date_created' => '2025-11-06T19:30:00.000Z',
        ];

        $this->mpClientMock
            ->shouldReceive('createRefund')
            ->once()
            ->with($paymentId, ['amount' => $partialAmount])
            ->andReturn($expectedRefund);

        // Act
        $result = $this->mercadoPagoStrategy->refund($paymentId, $partialAmount);

        // Assert
        $this->assertEquals('refund_456', $result['id']);
        $this->assertEquals($paymentId, $result['payment_id']);
        $this->assertEquals($partialAmount, $result['amount']);
        $this->assertEquals('approved', $result['status']);
    }

    public function test_refund_throws_exception(): void
    {
        // Arrange
        $paymentId = '123456789';
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Erro ao processar estorno: Refund Error');

        $this->mpClientMock
            ->shouldReceive('createRefund')
            ->once()
            ->andThrow(new Exception('Refund Error'));

        // Act
        $this->mercadoPagoStrategy->refund($paymentId);
    }

    public function test_cancel_payment_successfully(): void
    {
        // Arrange
        $paymentId = '123456789';
        $pendingPayment = [
            'id' => $paymentId,
            'status' => 'pending',
            'transaction_amount' => 100.00,
            'description' => 'Pending payment',
            'payment_method_id' => 'pix',
            'status_detail' => 'pending_waiting_payment',
            'metadata' => (object) [],
        ];

        $this->mpClientMock
            ->shouldReceive('getPayment')
            ->once()
            ->with($paymentId)
            ->andReturn((object) $pendingPayment);

        $cancelledPayment = (object) [
            'id' => $paymentId,
            'status' => 'cancelled',
            'transaction_amount' => 100.00,
            'description' => 'Payment cancelled',
            'payment_method_id' => 'pix',
            'status_detail' => 'by_collector',
            'metadata' => (object) [],
        ];

        $this->mpClientMock
            ->shouldReceive('cancelPayment')
            ->once()
            ->with($paymentId)
            ->andReturn($cancelledPayment);

        // Act
        $result = $this->mercadoPagoStrategy->cancel($paymentId);

        // Assert
        $this->assertEquals($paymentId, $result['id']);
        $this->assertEquals('cancelled', $result['status']);
    }

    public function test_cancel_payment_fails_if_not_pending(): void
    {
        // Arrange
        $paymentId = '123456789';
        $approvedPayment = [
            'id' => $paymentId,
            'status' => 'approved',
            'transaction_amount' => 100.00,
            'description' => 'Approved payment',
            'payment_method_id' => 'pix',
            'status_detail' => 'accredited',
            'metadata' => (object) [],
        ];

        $this->mpClientMock
            ->shouldReceive('getPayment')
            ->once()
            ->with($paymentId)
            ->andReturn((object) $approvedPayment);

        $this->mpClientMock
            ->shouldNotReceive('cancelPayment');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Pagamento não pode ser cancelado, pois não está pendente.');

        // Act
        $this->mercadoPagoStrategy->cancel($paymentId);
    }

    public function test_cancel_payment_throws_exception(): void
    {
        // Arrange
        $paymentId = '123456789';
        $pendingPayment = [
            'id' => $paymentId,
            'status' => 'pending',
            'transaction_amount' => 100.00,
            'description' => 'Pending payment',
            'payment_method_id' => 'pix',
            'status_detail' => 'pending_waiting_payment',
            'metadata' => (object) [],
        ];

        $this->mpClientMock
            ->shouldReceive('getPayment')
            ->once()
            ->with($paymentId)
            ->andReturn((object) $pendingPayment);

        $this->mpClientMock
            ->shouldReceive('cancelPayment')
            ->once()
            ->andThrow(new Exception('Cancel Error'));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Erro ao cancelar pagamento: Cancel Error');

        // Act
        $this->mercadoPagoStrategy->cancel($paymentId);
    }
}
