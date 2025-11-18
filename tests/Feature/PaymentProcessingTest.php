<?php

namespace UendelSilveira\PaymentModuleManager\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Mockery;
use Mockery\MockInterface;
use UendelSilveira\PaymentModuleManager\Contracts\PaymentGatewayInterface;
use UendelSilveira\PaymentModuleManager\DTOs\ProcessPaymentResponse;
use UendelSilveira\PaymentModuleManager\Enums\PaymentStatus;
use UendelSilveira\PaymentModuleManager\PaymentGatewayManager;
use UendelSilveira\PaymentModuleManager\Tests\TestCase;

class PaymentProcessingTest extends TestCase
{
    use DatabaseMigrations;

    private MockInterface $gatewayMock;

    private MockInterface $gatewayManagerMock;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock da interface do Gateway
        $this->gatewayMock = Mockery::mock(PaymentGatewayInterface::class);

        // Mock do Gateway Manager
        $this->gatewayManagerMock = Mockery::mock(PaymentGatewayManager::class);

        // Substituir a instância do PaymentGatewayManager no container de serviços do Laravel
        $this->app->instance(PaymentGatewayManager::class, $this->gatewayManagerMock);

        // Forçar a recriação do PaymentService para usar o mock do PaymentGatewayManager
        $this->app->forgetInstance(\UendelSilveira\PaymentModuleManager\Services\PaymentService::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_successfully_processes_a_payment_request(): void
    {
        // 1. Arrange (Organizar)

        // Configurar o mock do Gateway Manager para retornar nosso gateway mockado
        $this->gatewayManagerMock
            ->shouldReceive('gateway')
            ->with('mercadopago') // ou o gateway que você espera que seja chamado
            ->andReturn($this->gatewayMock);

        // Configurar o mock do Gateway para simular uma resposta de sucesso com o DTO
        $processPaymentResponse = new ProcessPaymentResponse(
            transactionId: 'mock_transaction_12345',
            status: PaymentStatus::APPROVED,
            details: ['original_response' => ['id' => 'mock_transaction_12345', 'status' => 'approved']]
        );

        $this->gatewayMock
            ->shouldReceive('processPayment')
            ->once()
            ->andReturn($processPaymentResponse);

        // Dados da requisição (mínimo válido segundo CreatePaymentRequest)
        $requestData = [
            'method' => 'mercadopago', // gateway dinâmico principal
            'payment_method_id' => 'pix',
            'amount' => 120.50,
            'description' => 'Test Product',
            'payer_email' => 'customer@example.com',
        ];

        // 2. Act (Agir)
        $testResponse = $this->postJson(route('payment.process'), $requestData);

        // 3. Assert (Verificar)

        // Verificar se a resposta da API foi de sucesso (201 Created)
        $testResponse->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Pagamento processado com sucesso.',
            ])
            ->assertJsonPath('data.status', PaymentStatus::APPROVED->value)
            ->assertJsonPath('data.external_id', 'mock_transaction_12345');

        // Verificar se a transação foi salva no banco de dados
        $this->assertDatabaseHas('transactions', [
            'gateway' => 'mercadopago',
            'amount' => 120.50,
            'status' => PaymentStatus::APPROVED->value,
            'external_id' => 'mock_transaction_12345',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_a_validation_error_if_amount_is_missing(): void
    {
        // 1. Arrange (mantém campos obrigatórios exceto amount)
        $requestData = [
            'method' => 'mercadopago',
            'payment_method_id' => 'pix',
            'description' => 'Test Product',
            'payer_email' => 'customer@example.com',
        ];

        // 2. Act
        $testResponse = $this->postJson(route('payment.process'), $requestData);

        // 3. Assert
        $testResponse->assertStatus(422) // HTTP 422 Unprocessable Entity
            ->assertJsonValidationErrors(['amount']);
    }
}
