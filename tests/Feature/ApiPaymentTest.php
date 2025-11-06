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
use UendelSilveira\PaymentModuleManager\Models\Transaction;
use UendelSilveira\PaymentModuleManager\Tests\TestCase;

class ApiPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_can_show_a_payment_and_update_status(): void
    {
        // 1. Criar uma transação local com status pendente
        $transaction = Transaction::create([
            'gateway' => PaymentGateway::MERCADOPAGO,
            'external_id' => 'mp_payment_to_show',
            'amount' => 150.00,
            'description' => 'Transaction to be updated',
            'status' => 'pending',
            'metadata' => ['payment_method_id' => 'pix'],
        ]);

        // 2. Mock do cliente do Mercado Pago para retornar um status diferente
        $this->instance(MercadoPagoClientInterface::class, Mockery::mock(MercadoPagoClientInterface::class, function ($mock): void {
            $mock->shouldReceive('getPayment')
                ->with('mp_payment_to_show')
                ->andReturn((object) [
                    'id' => 'mp_payment_to_show',
                    'status' => 'approved', // O gateway agora diz que foi aprovado
                    'transaction_amount' => 150.00,
                    'description' => 'Transaction to be updated',
                    'payment_method_id' => 'pix',
                    'status_detail' => 'accredited',
                    'metadata' => (object) [],
                ]);
        }));

        // 3. Chamar o endpoint de consulta
        $testResponse = $this->getJson(route('payment.show', ['transaction' => $transaction->id]));

        // 4. Verificar a resposta da API
        $testResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $transaction->id,
                    'status' => 'approved', // A resposta deve refletir o novo status
                ],
            ]);

        // 5. Verificar se o banco de dados foi atualizado
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => 'approved',
        ]);
    }

    public function test_it_can_process_a_pix_payment_successfully(): void
    {
        // Mock da MercadoPagoClientInterface para PIX
        $this->instance(MercadoPagoClientInterface::class, Mockery::mock(MercadoPagoClientInterface::class, function ($mock): void {
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

        $testResponse = $this->postJson(route('payment.process'), $payload);

        $testResponse->assertStatus(201)
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

    public function test_it_can_process_a_credit_card_payment_successfully(): void
    {
        // Mock da MercadoPagoClientInterface para Cartão de Crédito
        $this->instance(MercadoPagoClientInterface::class, Mockery::mock(MercadoPagoClientInterface::class, function ($mock): void {
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

        $testResponse = $this->postJson(route('payment.process'), $payload);

        $testResponse->assertStatus(201)
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

    public function test_it_can_process_a_credit_card_payment_with_installments_successfully(): void
    {
        // Mock da MercadoPagoClientInterface para Cartão de Crédito com parcelamento
        $this->instance(MercadoPagoClientInterface::class, Mockery::mock(MercadoPagoClientInterface::class, function ($mock): void {
            $mock->shouldReceive('createPayment')->andReturn((object) [
                'id' => 'mp_payment_id_cc_installments',
                'status' => 'approved',
                'transaction_amount' => 300.00,
                'description' => 'Teste de pagamento com Cartão Parcelado',
                'payment_method_id' => 'visa',
                'installments' => 3,
                'status_detail' => 'accredited',
                'metadata' => (object) [],
            ]);
        }));

        $payload = [
            'amount' => 300.00,
            'method' => PaymentGateway::MERCADOPAGO,
            'description' => 'Teste de pagamento com Cartão Parcelado',
            'payer_email' => 'test@example.com',
            'payment_method_id' => 'credit_card',
            'token' => 'mock_card_token',
            'installments' => 3,
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

        $testResponse = $this->postJson(route('payment.process'), $payload);

        $testResponse->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Pagamento processado com sucesso.',
                'data' => [
                    'gateway' => 'mercadopago',
                    'amount' => '300.00',
                    'status' => 'approved',
                ],
            ]);

        $this->assertDatabaseHas('transactions', [
            'gateway' => 'mercadopago',
            'amount' => 300.00,
            'status' => 'approved',
        ]);
    }

    public function test_it_can_process_a_boleto_payment_successfully(): void
    {
        // Mock da MercadoPagoClientInterface para Boleto
        $this->instance(MercadoPagoClientInterface::class, Mockery::mock(MercadoPagoClientInterface::class, function ($mock): void {
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

        $testResponse = $this->postJson(route('payment.process'), $payload);

        $testResponse->assertStatus(201)
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

    public function test_it_returns_error_for_unsupported_payment_method(): void
    {
        $payload = [
            'amount' => 100,
            'method' => 'pagseguro',
            'description' => 'Teste com gateway inválido',
            'payer_email' => 'test@example.com',
        ];

        $testResponse = $this->postJson(route('payment.process'), $payload);

        $testResponse->assertStatus(422);
    }
}
