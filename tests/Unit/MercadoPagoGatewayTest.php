<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 19/11/2025 14:24:12
*/

namespace UendelSilveira\PaymentModuleManager\Tests\Unit;

use MercadoPago\Exceptions\MPApiException;
use MercadoPago\Net\MPResponse;
use Mockery;
use UendelSilveira\PaymentModuleManager\DTOs\ProcessPaymentResponse;
use UendelSilveira\PaymentModuleManager\Enums\PaymentStatus;
use UendelSilveira\PaymentModuleManager\Exceptions\PaymentFailedException;
use UendelSilveira\PaymentModuleManager\Exceptions\TransactionNotFoundException;
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
        $processPaymentResponse = $mercadoPagoGateway->processPayment($paymentData);

        // 3. Assert
        $this->assertInstanceOf(ProcessPaymentResponse::class, $processPaymentResponse);
        $this->assertEquals('123456789', $processPaymentResponse->transactionId);
        $this->assertEquals(PaymentStatus::PENDING, $processPaymentResponse->status);
        $this->assertEquals('pix', $processPaymentResponse->details['payment_method']);
        $this->assertArrayHasKey('pix_qr_code', $processPaymentResponse->details);
        $this->assertArrayHasKey('pix_qr_code_base64', $processPaymentResponse->details);

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

        $mpResponse = new MPResponse(400, ['message' => 'Invalid payment data']);
        $mpApiException = new MPApiException('Invalid payment data', $mpResponse);

        $this->paymentClientStub->createException = $mpApiException;

        // 2. Assert
        $this->expectException(PaymentFailedException::class);
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
    public function it_throws_exception_when_fetching_status_for_nonexistent_transaction(): void
    {
        // 1. Arrange
        $mercadoPagoGateway = new MercadoPagoGateway($this->config);
        $mercadoPagoGateway->paymentClient = $this->paymentClientStub;

        $transactionId = 'invalid-id';
        $mpResponse = new MPResponse(404, ['message' => 'Not found']);
        $mpApiException = new MPApiException('Not found', $mpResponse);
        $this->paymentClientStub->getException = $mpApiException;

        // 2. Assert
        $this->expectException(TransactionNotFoundException::class);

        // 3. Act
        $mercadoPagoGateway->getPaymentStatus($transactionId);
    }
}
