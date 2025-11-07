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

    public function test_charge_with_pix_successfully(): void
    {
        // Arrange
        $amount = 100.0;
        $data = [
            'payment_method_id' => 'pix',
            'payer_email' => 'test@example.com',
            'description' => 'Test PIX Payment',
        ];

        $expectedPaymentResponse = (object) [
            'id' => 12345,
            'status' => 'pending',
            'transaction_amount' => $amount,
            'description' => 'Test PIX Payment',
            'payment_method_id' => 'pix',
            'status_detail' => 'pending_waiting_payment',
            'metadata' => null,
            'point_of_interaction' => (object) [
                'transaction_data' => (object) [
                    'qr_code_base64' => 'test_qr_code_base64',
                ],
            ],
        ];

        $this->mpClientMock
            ->shouldReceive('createPayment')
            ->once()
            ->with(Mockery::on(function ($payload) use ($amount) {
                return $payload['payment_method_id'] === 'pix'
                    && $payload['transaction_amount'] === $amount;
            }))
            ->andReturn($expectedPaymentResponse);

        // Act
        $result = $this->mercadoPagoStrategy->charge($amount, $data);

        // Assert
        $this->assertEquals(12345, $result['id']);
        $this->assertEquals('pending', $result['status']);
        $this->assertEquals('test_qr_code_base64', $result['external_resource_url']);
    }

    public function test_charge_with_boleto_successfully(): void
    {
        // Arrange
        $amount = 150.0;
        $data = [
            'payment_method_id' => 'boleto',
            'payer_email' => 'boleto@example.com',
            'description' => 'Test Boleto Payment',
            'payer' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'identification' => ['type' => 'CPF', 'number' => '12345678909'],
                'address' => [
                    'zip_code' => '01001000',
                    'street_name' => 'Praça da Sé',
                    'street_number' => 's/n',
                    'neighborhood' => 'Sé',
                    'city' => 'São Paulo',
                    'federal_unit' => 'SP',
                ],
            ],
        ];

        $expectedPaymentResponse = (object) [
            'id' => 54321,
            'status' => 'pending',
            'transaction_amount' => $amount,
            'description' => 'Test Boleto Payment',
            'payment_method_id' => 'boleto',
            'status_detail' => 'pending_waiting_payment',
            'metadata' => null,
            'point_of_interaction' => (object) [
                'transaction_data' => (object) [
                    'ticket_url' => 'https://example.com/boleto/123',
                ],
            ],
        ];

        $this->mpClientMock
            ->shouldReceive('createPayment')
            ->once()
            ->with(Mockery::on(function ($payload) {
                return $payload['payment_method_id'] === 'boleto'
                    && $payload['payer']['first_name'] === 'John';
            }))
            ->andReturn($expectedPaymentResponse);

        // Act
        $result = $this->mercadoPagoStrategy->charge($amount, $data);

        // Assert
        $this->assertEquals(54321, $result['id']);
        $this->assertEquals('pending', $result['status']);
        $this->assertEquals('https://example.com/boleto/123', $result['external_resource_url']);
    }

    public function test_charge_with_credit_card_successfully(): void
    {
        // Arrange
        $amount = 200.0;
        $data = [
            'payment_method_id' => 'credit_card',
            'payer_email' => 'cc@example.com',
            'description' => 'Test Credit Card Payment',
            'token' => 'card_token_123',
            'installments' => 1,
            'issuer_id' => '321',
            'payer' => [
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'identification' => ['type' => 'CPF', 'number' => '98765432101'],
            ],
        ];

        $expectedPaymentResponse = (object) [
            'id' => 67890,
            'status' => 'approved',
            'transaction_amount' => $amount,
            'description' => 'Test Credit Card Payment',
            'payment_method_id' => 'credit_card',
            'status_detail' => 'accredited',
            'metadata' => null,
        ];

        $this->mpClientMock
            ->shouldReceive('createPayment')
            ->once()
            ->with(Mockery::on(function ($payload) {
                return $payload['payment_method_id'] === 'credit_card'
                    && $payload['token'] === 'card_token_123';
            }))
            ->andReturn($expectedPaymentResponse);

        // Act
        $result = $this->mercadoPagoStrategy->charge($amount, $data);

        // Assert
        $this->assertEquals(67890, $result['id']);
        $this->assertEquals('approved', $result['status']);
        $this->assertArrayNotHasKey('external_resource_url', $result);
    }

    public function test_charge_throws_exception(): void
    {
        // Arrange
        $amount = 100.0;
        $data = [
            'payment_method_id' => 'pix',
            'payer_email' => 'test@example.com',
        ];

        $this->mpClientMock
            ->shouldReceive('createPayment')
            ->once()
            ->andThrow(new Exception('Payment processor error'));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Erro ao processar pagamento: Payment processor error');

        // Act
        $this->mercadoPagoStrategy->charge($amount, $data);
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
