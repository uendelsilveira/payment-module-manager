<?php

namespace UendelSilveira\PaymentModuleManager\Tests\Feature;

use MercadoPago\Exceptions\MPApiException;
use MercadoPago\Net\MPResponse;
use MercadoPago\Resources\Payment;
use Mockery;
use Mockery\MockInterface;
use UendelSilveira\PaymentModuleManager\Contracts\MercadoPagoPaymentClientInterface;
use UendelSilveira\PaymentModuleManager\DTOs\ProcessPaymentResponse;
use UendelSilveira\PaymentModuleManager\Enums\PaymentStatus;
use UendelSilveira\PaymentModuleManager\Exceptions\PaymentFailedException;
use UendelSilveira\PaymentModuleManager\Exceptions\TransactionNotFoundException;
use UendelSilveira\PaymentModuleManager\Gateways\MercadoPagoGateway;
use UendelSilveira\PaymentModuleManager\Tests\TestCase;

class MercadoPagoGatewayTest extends TestCase
{
    private array $config;

    /**
     * @var MockInterface|MercadoPagoPaymentClientInterface
     */
    private MockInterface $paymentClientMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = [
            'class' => MercadoPagoGateway::class,
            'access_token' => 'TEST-YOUR-TOKEN',
            'sandbox' => true,
        ];

        // Criar um mock da interface
        $this->paymentClientMock = Mockery::mock(MercadoPagoPaymentClientInterface::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_successfully_processes_a_pix_payment(): void
    {
        // Define a mock webhook URL generator
        $webhookUrlGenerator = function ($gateway) {
            return "https://example.com/webhook/{$gateway}";
        };

        // Passa o mock diretamente para o construtor
        $mercadoPagoGateway = new MercadoPagoGateway($this->config, $this->paymentClientMock, $webhookUrlGenerator);

        $paymentData = [
            'amount' => 150.50,
            'payment_method_id' => 'pix',
            'payer_email' => 'test@example.com',
            'description' => 'Test PIX payment',
        ];

        $sdkResponse = new Payment;
        $sdkResponse->id = 123456789;
        $sdkResponse->status = 'pending';
        $sdkResponse->payment_method_id = 'pix';
        $sdkResponse->transaction_amount = 150.50;
        $sdkResponse->date_created = now()->toIso8601String();
        $sdkResponse->point_of_interaction = (object) [
            'transaction_data' => (object) [
                'qr_code' => '00020126...',
                'qr_code_base64' => 'iVBORw0KGgoAAA...',
            ],
        ];

        $this->paymentClientMock
            ->shouldReceive('create')
            ->once()
            ->andReturn($sdkResponse);

        $response = $mercadoPagoGateway->processPayment($paymentData);

        $this->assertInstanceOf(ProcessPaymentResponse::class, $response);
        $this->assertEquals('123456789', $response->transactionId);
        $this->assertEquals(PaymentStatus::PENDING, $response->status);
        $this->assertEquals('pix', $response->details['payment_method']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_throws_a_gateway_exception_on_api_failure(): void
    {
        // Define a mock webhook URL generator
        $webhookUrlGenerator = function ($gateway) {
            return "https://example.com/webhook/{$gateway}";
        };

        $mercadoPagoGateway = new MercadoPagoGateway($this->config, $this->paymentClientMock, $webhookUrlGenerator);

        $paymentData = [
            'amount' => 100.00,
            'payment_method_id' => 'pix',
            'payer_email' => 'test@example.com',
        ];

        $mpResponse = new MPResponse(400, ['message' => 'Invalid payment data']);
        $mpApiException = new MPApiException('Invalid payment data', $mpResponse);

        $this->paymentClientMock
            ->shouldReceive('create')
            ->once()
            ->andThrow($mpApiException);

        $this->expectException(PaymentFailedException::class);
        $this->expectExceptionMessageMatches('/mercado pago error/i');

        $mercadoPagoGateway->processPayment($paymentData);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_correctly_gets_payment_status(): void
    {
        // Define a mock webhook URL generator
        $webhookUrlGenerator = function ($gateway) {
            return "https://example.com/webhook/{$gateway}";
        };

        $mercadoPagoGateway = new MercadoPagoGateway($this->config, $this->paymentClientMock, $webhookUrlGenerator);

        $transactionId = '12345';
        $sdkResponse = new Payment;
        $sdkResponse->id = 12345;
        $sdkResponse->status = 'approved';

        $this->paymentClientMock
            ->shouldReceive('get')
            ->once()
            ->with((int) $transactionId)
            ->andReturn($sdkResponse);

        $paymentStatus = $mercadoPagoGateway->getPaymentStatus($transactionId);

        $this->assertEquals(PaymentStatus::APPROVED, $paymentStatus);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_throws_exception_when_fetching_status_for_nonexistent_transaction(): void
    {
        // Define a mock webhook URL generator
        $webhookUrlGenerator = function ($gateway) {
            return "https://example.com/webhook/{$gateway}";
        };

        $mercadoPagoGateway = new MercadoPagoGateway($this->config, $this->paymentClientMock, $webhookUrlGenerator);

        $transactionId = 'invalid-id';
        $mpResponse = new MPResponse(404, ['message' => 'Not found']);
        $mpApiException = new MPApiException('Not Found', $mpResponse);

        $this->paymentClientMock
            ->shouldReceive('get')
            ->once()
            ->with(Mockery::any())
            ->andThrow($mpApiException);

        $this->expectException(TransactionNotFoundException::class);

        $mercadoPagoGateway->getPaymentStatus($transactionId);
    }
}
