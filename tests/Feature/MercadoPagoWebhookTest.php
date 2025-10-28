<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 28/10/2025 20:43:21
*/

namespace UendelSilveira\PaymentModuleManager\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use MercadoPago\MercadoPagoConfig;
use Mockery;
use UendelSilveira\PaymentModuleManager\Contracts\MercadoPagoClientInterface;
use UendelSilveira\PaymentModuleManager\Models\Transaction;
use UendelSilveira\PaymentModuleManager\Tests\TestCase;

class MercadoPagoWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Configura o access token para o SDK do MP, mesmo que seja um mock.
        // Isso é necessário porque o construtor do MercadoPagoClient ainda tenta configurar o SDK.
        MercadoPagoConfig::setAccessToken('TEST_ACCESS_TOKEN');

        // Mock da interface MercadoPagoClientInterface
        $this->instance(MercadoPagoClientInterface::class, Mockery::mock(MercadoPagoClientInterface::class, function ($mock) {
            $mock->shouldReceive('getPayment')->andReturn((object) [
                'id' => 'mp_payment_id_123',
                'status' => 'approved',
                'transaction_amount' => 100.00,
                'description' => 'Webhook Test Payment',
                'payment_method_id' => 'pix',
                'status_detail' => 'accredited',
                'metadata' => (object) [],
            ]);
        }));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_webhook_updates_transaction_status_to_approved()
    {
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
                'id' => 'mp_payment_id_123', // ID do pagamento que será consultado
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
            'status' => 'approved', // Esperamos que o status tenha sido atualizado
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
