<?php

namespace Us\PaymentModuleManager\Tests\Feature;

use Us\PaymentModuleManager\Enums\PaymentGateway;
use Us\PaymentModuleManager\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ApiPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_process_a_mercadopago_payment_successfully()
    {
        $payload = [
            'amount' => 150.75,
            'method' => PaymentGateway::MERCADOPAGO,
            'description' => 'Teste de pagamento com Mercado Pago',
        ];

        $response = $this->postJson('/api/payment/process', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Pagamento processado com sucesso.',
                'data' => [
                    'gateway' => 'mercadopago',
                    'amount' => '150.75',
                    'status' => 'approved',
                ],
            ]);

        $this->assertDatabaseHas('transactions', [
            'gateway' => 'mercadopago',
            'amount' => 150.75,
            'status' => 'approved',
        ]);
    }

    public function test_it_returns_error_for_unsupported_payment_method()
    {
        $payload = [
            'amount' => 100,
            'method' => 'pagseguro', // Método não suportado
            'description' => 'Teste com gateway inválido',
        ];

        $response = $this->postJson('/api/payment/process', $payload);

        $response->assertStatus(422); // Falha na validação
    }
}
