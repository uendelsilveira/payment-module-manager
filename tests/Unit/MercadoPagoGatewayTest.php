<?php

namespace UendelSilveira\PaymentModuleManager\Tests\Unit;

use MercadoPago\Exceptions\MPApiException;
use MercadoPago\Net\MPResponse;
use Mockery;
use UendelSilveira\PaymentModuleManager\Enums\PaymentStatus;
use UendelSilveira\PaymentModuleManager\Exceptions\PaymentGatewayException;
use UendelSilveira\PaymentModuleManager\Gateways\MercadoPagoGateway;
use UendelSilveira\PaymentModuleManager\Tests\TestCase;

class MercadoPagoGatewayTest extends TestCase
{
    private array $config;

    private object $paymentClientStub;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = [
            'class' => MercadoPagoGateway::class,
            'access_token' => 'TEST-YOUR-TOKEN',
            'sandbox' => true,
        ];

        // Stub simples para substituir o PaymentClient final
        $this->paymentClientStub = new class
        {
            public array $lastCreateRequest = [];

            public mixed $createResponse = null;

            public mixed $getResponse = null;

            public mixed $cancelResponse = null;

            public ?\Throwable $createException = null;

            public ?\Throwable $getException = null;

            public ?\Throwable $cancelException = null;

            public function create(array $request): object
            {
                $this->lastCreateRequest = $request;

                if ($this->createException instanceof \Throwable) {
                    throw $this->createException;
                }

                return is_object($this->createResponse) ? $this->createResponse : (object) $this->createResponse;
            }

            public function get(int $id): object
            {
                if ($this->getException instanceof \Throwable) {
                    throw $this->getException;
                }

                return is_object($this->getResponse) ? $this->getResponse : (object) $this->getResponse;
            }

            public function cancel(int $id): object
            {
                if ($this->cancelException instanceof \Throwable) {
                    throw $this->cancelException;
                }

                return is_object($this->cancelResponse) ? $this->cancelResponse : (object) $this->cancelResponse;
            }
        };
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_successfully_processes_a_pix_payment(): void
    {
        // 1. Arrange
        $mercadoPagoGateway = new MercadoPagoGateway($this->config);
        $mercadoPagoGateway->paymentClient = $this->paymentClientStub; // Injeção do stub

        $paymentData = [
            'amount' => 150.50,
            'payment_method_id' => 'pix',
            'payer_email' => 'test@example.com',
            'description' => 'Test PIX payment',
            'webhook_url' => 'https://example.test/webhook',
        ];

        $sdkResponse = (object) [
            'id' => 123456789,
            'status' => 'pending',
            'payment_method_id' => 'pix',
            'transaction_amount' => 150.50,
            'date_created' => now()->toIso8601String(),
            'point_of_interaction' => (object) [
                'transaction_data' => (object) [
                    'qr_code' => '00020126...',
                    'qr_code_base64' => 'iVBORw0KGgoAAA...',
                ],
            ],
        ];

        $this->paymentClientStub->createResponse = $sdkResponse;

        // 2. Act
        $result = $mercadoPagoGateway->processPayment($paymentData);

        // 3. Assert
        $this->assertIsArray($result);
        $this->assertEquals('123456789', $result['transaction_id']);
        $this->assertEquals(PaymentStatus::PENDING, $result['status']);
        $this->assertEquals('pix', $result['payment_method']);
        $this->assertArrayHasKey('pix_qr_code', $result);
        $this->assertArrayHasKey('pix_qr_code_base64', $result);

        // Verifica request enviado ao stub
        $sent = $this->paymentClientStub->lastCreateRequest;
        $this->assertEquals($paymentData['amount'], $sent['transaction_amount']);
        $this->assertEquals('pix', $sent['payment_method_id']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_throws_a_gateway_exception_on_api_failure(): void
    {
        // 1. Arrange
        $mercadoPagoGateway = new MercadoPagoGateway($this->config);
        $mercadoPagoGateway->paymentClient = $this->paymentClientStub;

        $paymentData = [
            'amount' => 100.00,
            'payment_method_id' => 'pix',
            'payer_email' => 'test@example.com',
            'webhook_url' => 'https://example.test/webhook',
        ];

        // Simular uma MPApiException (sem invocar construtor real) E com métodos esperados
        $mpResponse = new MPResponse(400, ['message' => 'Invalid payment data']);
        $mock = Mockery::mock(MPApiException::class);
        $mock->shouldReceive('getMessage')->andReturn('Invalid payment data');
        $mock->shouldReceive('getStatusCode')->andReturn(400);
        $mock->shouldReceive('getApiResponse')->andReturn($mpResponse);
        $this->paymentClientStub->createException = $mock;

        // 2. Assert
        $this->expectException(PaymentGatewayException::class);
        $this->expectExceptionMessageMatches('/mercado pago error/i');

        // 3. Act
        $mercadoPagoGateway->processPayment($paymentData);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_correctly_gets_payment_status(): void
    {
        // 1. Arrange
        $mercadoPagoGateway = new MercadoPagoGateway($this->config);
        $mercadoPagoGateway->paymentClient = $this->paymentClientStub;

        $transactionId = '12345';
        $sdkResponse = (object) ['id' => 12345, 'status' => 'approved'];

        $this->paymentClientStub->getResponse = $sdkResponse;

        // 2. Act
        $paymentStatus = $mercadoPagoGateway->getPaymentStatus($transactionId);

        // 3. Assert
        $this->assertEquals(PaymentStatus::APPROVED, $paymentStatus);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_unknown_status_when_fetching_status_fails(): void
    {
        // 1. Arrange
        $mercadoPagoGateway = new MercadoPagoGateway($this->config);
        $mercadoPagoGateway->paymentClient = $this->paymentClientStub;

        $transactionId = 'invalid-id';
        $mpResponse = new MPResponse(404, ['message' => 'Not found']);
        $mock = Mockery::mock(MPApiException::class);
        $mock->shouldReceive('getMessage')->andReturn('Not found');
        $mock->shouldReceive('getStatusCode')->andReturn(404);
        $mock->shouldReceive('getApiResponse')->andReturn($mpResponse);
        $this->paymentClientStub->getException = $mock;

        // 2. Act
        $paymentStatus = $mercadoPagoGateway->getPaymentStatus($transactionId);

        // 3. Assert
        $this->assertEquals(PaymentStatus::UNKNOWN, $paymentStatus);
    }
}
