<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 28/10/2025 20:43:21
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

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_can_process_a_pix_payment_successfully()
    {
        // Mock da MercadoPagoClientInterface para PIX
        $this->instance(MercadoPagoClientInterface::class, Mockery::mock(MercadoPagoClientInterface::class, function ($mock) {
            $mock->shouldReceive('createPayment')->andReturn((object) [
                'id' => 'mp_payment_id_pix',
                'status' => 'pending', // PIX começa como pendente
                'transaction_amount' => 150.75,
                'description' => 'Teste de pagamento com PIX',
                'payment_method_id' => 'pix',
                'status_detail' => 'pending_waiting_transfer',
                'metadata' => (object) [],
                'point_of_interaction' => (object) [
                    'transaction_data' => (object) [
                        'qr_code_base64' => 'mocked_qr_code_base64',
                    ],
                ],
            ]);
        }));

        $payload = [
            'amount' => 150.75,
            'method' => PaymentGateway::MERCADOPAGO,
            'description' => 'Teste de pagamento com PIX',
            'payer_email' => 'test@example.com',
            'payment_method_id' => 'pix',
        ];

        $response = $this->postJson(route('payment.process'), $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Pagamento processado com sucesso.',
                'data' => [
                    'gateway' => 'mercadopago',
                    'amount' => '150.75',
                    'status' => 'pending',
                ],
            ]);

        $this->assertDatabaseHas('transactions', [
            'gateway' => 'mercadopago',
            'amount' => 150.75,
            'status' => 'pending',
        ]);
    }

    public function test_it_can_process_a_credit_card_payment_successfully()
    {
        // Mock da MercadoPagoClientInterface para Cartão de Crédito
        $this->instance(MercadoPagoClientInterface::class, Mockery::mock(MercadoPagoClientInterface::class, function ($mock) {
            $mock->shouldReceive('createPayment')->andReturn((object) [
                'id' => 'mp_payment_id_cc',
                'status' => 'approved', // Cartão geralmente aprova na hora
                'transaction_amount' => 250.00,
                'description' => 'Teste de pagamento com Cartão',
                'payment_method_id' => 'visa',
                'status_detail' => 'accredited',
                'metadata' => (object) [],
            ]);
        }));

        $payload = [
            'amount' => 250.00,
            'method' => PaymentGateway::MERCADOPAGO,
            'description' => 'Teste de pagamento com Cartão',
            'payer_email' => 'test@example.com',
            'payment_method_id' => 'credit_card',
            'token' => 'mock_card_token',
            'installments' => 1,
            'issuer_id' => 'mock_issuer_id',
            'payer' => [
                'first_name' => 'Test',
                'last_name' => 'User',
                'identification' => [
                    'type' => 'CPF',
                    'number' => '12345678909',
                ],
            ],
        ];

        $response = $this->postJson(route('payment.process'), $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Pagamento processado com sucesso.',
                'data' => [
                    'gateway' => 'mercadopago',
                    'amount' => '250.00',
                    'status' => 'approved',
                ],
            ]);

        $this->assertDatabaseHas('transactions', [
            'gateway' => 'mercadopago',
            'amount' => 250.00,
            'status' => 'approved',
        ]);
    }

    public function test_it_can_process_a_boleto_payment_successfully()
    {
        // Mock da MercadoPagoClientInterface para Boleto
        $this->instance(MercadoPagoClientInterface::class, Mockery::mock(MercadoPagoClientInterface::class, function ($mock) {
            $mock->shouldReceive('createPayment')->andReturn((object) [
                'id' => 'mp_payment_id_boleto',
                'status' => 'pending', // Boleto começa como pendente
                'transaction_amount' => 100.00,
                'description' => 'Teste de pagamento com Boleto',
                'payment_method_id' => 'bolbradesco',
                'status_detail' => 'pending_waiting_payment',
                'metadata' => (object) [],
                'point_of_interaction' => (object) [
                    'transaction_data' => (object) [
                        'ticket_url' => 'https://mocked.boleto.url/123',
                    ],
                ],
            ]);
        }));

        $payload = [
            'amount' => 100.00,
            'method' => PaymentGateway::MERCADOPAGO,
            'description' => 'Teste de pagamento com Boleto',
            'payer_email' => 'test@example.com',
            'payment_method_id' => 'boleto',
            'payer' => [
                'first_name' => 'Boleto',
                'last_name' => 'User',
                'identification' => [
                    'type' => 'CPF',
                    'number' => '11122233344',
                ],
                'address' => [
                    'zip_code' => '01000000',
                    'street_name' => 'Rua Teste',
                    'street_number' => '123',
                    'neighborhood' => 'Centro',
                    'city' => 'Sao Paulo',
                    'federal_unit' => 'SP',
                ],
            ],
        ];

        $response = $this->postJson(route('payment.process'), $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Pagamento processado com sucesso.',
                'data' => [
                    'gateway' => 'mercadopago',
                    'amount' => '100.00',
                    'status' => 'pending',
                ],
            ]);

        $this->assertDatabaseHas('transactions', [
            'gateway' => 'mercadopago',
            'amount' => 100.00,
            'status' => 'pending',
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
