<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 28/10/2025 20:57:39
*/

namespace UendelSilveira\PaymentModuleManager\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use UendelSilveira\PaymentModuleManager\Contracts\MercadoPagoClientInterface;
use UendelSilveira\PaymentModuleManager\Enums\PaymentGateway;
use UendelSilveira\PaymentModuleManager\Tests\TestCase;

class ApiPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock da MercadoPagoClientInterface para evitar chamadas reais à API do Mercado Pago
        $this->instance(MercadoPagoClientInterface::class, Mockery::mock(MercadoPagoClientInterface::class, function ($mock) {
            $mock->shouldReceive('createPayment')->andReturn((object) [
                'id' => 'mp_payment_id_test',
                'status' => 'approved',
                'transaction_amount' => 150.75,
                'description' => 'Teste de pagamento com Mercado Pago',
                'payment_method_id' => 'pix',
                'status_detail' => 'accredited',
                'metadata' => (object) [],
                'point_of_interaction' => (object) [
                    'transaction_data' => (object) [
                        'qr_code_base64' => 'mocked_qr_code_base64',
                    ],
                ],
            ]);
        }));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_can_process_a_mercadopago_payment_successfully()
    {
        $payload = [
            'amount' => 150.75,
            'method' => PaymentGateway::MERCADOPAGO,
            'description' => 'Teste de pagamento com Mercado Pago',
            'payer_email' => 'test@example.com',
        ];

        $response = $this->postJson(route('payment.process'), $payload);

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
            'method' => 'pagseguro',
            'description' => 'Teste com gateway inválido',
            'payer_email' => 'test@example.com',
        ];

        $response = $this->postJson(route('payment.process'), $payload);

        $response->assertStatus(422);
    }
}
