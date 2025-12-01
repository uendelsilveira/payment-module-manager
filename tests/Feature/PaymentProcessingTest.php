<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 19/11/2025 14:24:12
*/

namespace UendelSilveira\PaymentModuleManager\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Mockery\MockInterface;
use UendelSilveira\PaymentModuleManager\Contracts\PaymentGatewayInterface;
use UendelSilveira\PaymentModuleManager\DTOs\ProcessPaymentResponse;
use UendelSilveira\PaymentModuleManager\Enums\PaymentStatus;
use UendelSilveira\PaymentModuleManager\PaymentGatewayManager;
use UendelSilveira\PaymentModuleManager\Tests\Models\User;
use UendelSilveira\PaymentModuleManager\Tests\TestCase;

class PaymentProcessingTest extends TestCase
{
    use DatabaseMigrations;

    private MockInterface $gatewayMock;

    private MockInterface $gatewayManagerMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gatewayMock = Mockery::mock(PaymentGatewayInterface::class);
        $this->gatewayManagerMock = Mockery::mock(PaymentGatewayManager::class);

        // Adiciona a expectativa que faltava:
        $this->gatewayManagerMock
            ->shouldReceive('getDefaultGateway')
            ->andReturn('mercadopago');

        $this->app->instance(PaymentGatewayManager::class, $this->gatewayManagerMock);
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
        $user = new User;
        $user->id = 1;
        Sanctum::actingAs($user);

        $this->gatewayManagerMock
            ->shouldReceive('gateway')
            ->with('mercadopago')
            ->andReturn($this->gatewayMock);

        $processPaymentResponse = new ProcessPaymentResponse(
            transactionId: 'mock_transaction_12345',
            status: PaymentStatus::APPROVED,
            details: ['original_response' => ['id' => 'mock_transaction_12345', 'status' => 'approved']]
        );

        $this->gatewayMock
            ->shouldReceive('processPayment')
            ->once()
            ->andReturn($processPaymentResponse);

        $requestData = [
            'method' => 'mercadopago',
            'payment_method_id' => 'pix',
            'amount' => 120.50,
            'description' => 'Test Product',
            'payer_email' => 'customer@example.com',
        ];

        $testResponse = $this->postJson(route('payment.process'), $requestData);

        $testResponse->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Pagamento processado com sucesso.',
            ])
            ->assertJsonPath('data.status', PaymentStatus::APPROVED->value)
            ->assertJsonPath('data.external_id', 'mock_transaction_12345');

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
        $user = new User;
        $user->id = 1;
        Sanctum::actingAs($user);

        $requestData = [
            'method' => 'mercadopago',
            'payment_method_id' => 'pix',
            'description' => 'Test Product',
            'payer_email' => 'customer@example.com',
        ];

        $testResponse = $this->postJson(route('payment.process'), $requestData);

        $testResponse->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_a_validation_error_if_amount_is_invalid(): void
    {
        $user = new User;
        $user->id = 1;
        Sanctum::actingAs($user);

        $requestDataZero = [
            'method' => 'mercadopago',
            'payment_method_id' => 'pix',
            'amount' => 0,
            'description' => 'Test Product',
            'payer_email' => 'customer@example.com',
        ];
        $this->postJson(route('payment.process'), $requestDataZero)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);

        $requestDataNegative = [
            'method' => 'mercadopago',
            'payment_method_id' => 'pix',
            'amount' => -10.50,
            'description' => 'Test Product',
            'payer_email' => 'customer@example.com',
        ];
        $this->postJson(route('payment.process'), $requestDataNegative)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }
}
