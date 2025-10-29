<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 28/10/2025 20:43:21
*/

namespace UendelSilveira\PaymentModuleManager\Tests\Feature;

use UendelSilveira\PaymentModuleManager\Models\Transaction;
use UendelSilveira\PaymentModuleManager\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use UendelSilveira\PaymentModuleManager\Contracts\MercadoPagoClientInterface;
use MercadoPago\MercadoPagoConfig;
use Mockery;

class MercadoPagoWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Configura o access token para o SDK do MP, mesmo que seja um mock.
        MercadoPagoConfig::setAccessToken('TEST_ACCESS_TOKEN');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_webhook_updates_transaction_status_to_approved()
    {
        // Mock do MercadoPagoClientInterface para simular um pagamento aprovado
        $this->instance(MercadoPagoClientInterface::class, Mockery::mock(MercadoPagoClientInterface::class, function ($mock) {
            $mock->shouldReceive('getPayment')->andReturn((object)[
                'id' => 'mp_payment_id_123',
                'status' => 'approved',
                'transaction_amount' => 100.00,
                'description' => 'Webhook Test Payment',
                'payment_method_id' => 'pix',
                'status_detail' => 'accredited',
                'metadata' => (object)[],
            ]);
        }));

        // 1. Criar uma transação pendente no banco de dados
        $transaction = Transaction::create([
            'external_id' => 'mp_payment_id_123',
            'gateway' => 'mercadopago',
            'amount' => 100.00,
            'currency' => 'BRL',
            'status' => 'pending',
            'description' => 'Webhook Test Payment',
            'metadata' => [],
        ]);

        // 2. Simular uma notificação de webhook do Mercado Pago
        $webhookPayload = [
            'id' => '258784569',
            'live_mode' => false,
            'type' => 'payment',
            'date_created' => '2025-10-27T10:00:00.000-04:00',
            'application_id' => '123456789',
            'user_id' => '987654321',
            'version' => 1,
            'api_version' => 'v1',
            'action' => 'payment.updated',
            'data' => [
                'id' => 'mp_payment_id_123',
            ],
        ];

        // 3. Enviar a requisição do webhook para o endpoint
        $response = $this->postJson(route('mercadopago.webhook'), $webhookPayload);

        // 4. Verificar a resposta do webhook
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Webhook processado com sucesso.',
            ]);

        // 5. Verificar se o status da transação foi atualizado no banco de dados
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'external_id' => 'mp_payment_id_123',
            'status' => 'approved',
        ]);
    }

    public function test_webhook_updates_transaction_status_to_refunded()
    {
        // Mock do MercadoPagoClientInterface para simular um pagamento reembolsado
        $this->instance(MercadoPagoClientInterface::class, Mockery::mock(MercadoPagoClientInterface::class, function ($mock) {
            $mock->shouldReceive('getPayment')->andReturn((object)[
                'id' => 'mp_payment_id_refunded',
                'status' => 'refunded',
                'transaction_amount' => 50.00,
                'description' => 'Webhook Test Refund',
                'payment_method_id' => 'pix',
                'status_detail' => 'refunded',
                'metadata' => (object)[],
            ]);
        }));

        // 1. Criar uma transação aprovada no banco de dados
        $transaction = Transaction::create([
            'external_id' => 'mp_payment_id_refunded',
            'gateway' => 'mercadopago',
            'amount' => 50.00,
            'currency' => 'BRL',
            'status' => 'approved',
            'description' => 'Webhook Test Refund',
            'metadata' => [],
        ]);

        // 2. Simular uma notificação de webhook do Mercado Pago para reembolso
        $webhookPayload = [
            'id' => '258784570',
            'live_mode' => false,
            'type' => 'payment',
            'date_created' => '2025-10-27T10:05:00.000-04:00',
            'application_id' => '123456789',
            'user_id' => '987654321',
            'version' => 1,
            'api_version' => 'v1',
            'action' => 'payment.refunded',
            'data' => [
                'id' => 'mp_payment_id_refunded',
            ],
        ];

        // 3. Enviar a requisição do webhook para o endpoint
        $response = $this->postJson(route('mercadopago.webhook'), $webhookPayload);

        // 4. Verificar a resposta do webhook
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Webhook processado com sucesso.',
            ]);

        // 5. Verificar se o status da transação foi atualizado no banco de dados
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'external_id' => 'mp_payment_id_refunded',
            'status' => 'refunded',
        ]);
    }

    public function test_webhook_returns_error_for_unsupported_notification_type()
    {
        $webhookPayload = [
            'type' => 'merchant_order',
            'data' => [
                'id' => '123',
            ],
        ];

        $response = $this->postJson(route('mercadopago.webhook'), $webhookPayload);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Tipo de notificação não suportado.',
            ]);
    }

    public function test_webhook_returns_error_if_payment_id_is_missing()
    {
        $webhookPayload = [
            'type' => 'payment',
            'data' => [], // ID do pagamento ausente
        ];

        $response = $this->postJson(route('mercadopago.webhook'), $webhookPayload);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'ID do pagamento não encontrado na notificação.',
            ]);
    }
}
