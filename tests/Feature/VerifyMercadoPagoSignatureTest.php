<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 28/10/2025 20:43:21
*/

namespace UendelSilveira\PaymentModuleManager\Tests\Feature;

use Illuminate\Support\Facades\Config;
use Mockery;
use UendelSilveira\PaymentModuleManager\Contracts\MercadoPagoClientInterface;
use UendelSilveira\PaymentModuleManager\Tests\TestCase;

class VerifyMercadoPagoSignatureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Define um secret para os testes
        Config::set('payment.gateways.mercadopago.webhook_secret', 'test_webhook_secret');
    }

    public function test_it_aborts_if_signature_header_is_missing()
    {
        $response = $this->postJson(route('mercadopago.webhook'), []);

        $response->assertStatus(403);
    }

    public function test_it_aborts_if_signature_is_invalid()
    {
        $payload = ['data' => ['id' => '12345']];

        $response = $this->withHeaders([
            'x-signature' => 'ts=12345,v1=invalid_signature',
        ])->postJson(route('mercadopago.webhook'), $payload);

        $response->assertStatus(403);
    }

    public function test_it_allows_request_with_valid_signature()
    {
        // Mock da dependência do controller para evitar chamadas reais à API
        $this->instance(MercadoPagoClientInterface::class, Mockery::mock(MercadoPagoClientInterface::class, function ($mock) {
            // O método getPayment não será chamado neste teste, mas é bom ter um mock para ele
            $mock->shouldReceive('getPayment')->andReturn((object) []);
        }));

        $payload = ['data' => ['id' => '12345']];
        $ts = time();
        $secret = Config::get('payment.gateways.mercadopago.webhook_secret');

        $manifest = "id:{$payload['data']['id']};request-id:{$ts};ts:{$ts};".json_encode($payload);
        $signature = hash_hmac('sha256', $manifest, $secret);

        $response = $this->withHeaders([
            'x-signature' => "ts={$ts},v1={$signature}",
        ])->postJson(route('mercadopago.webhook'), $payload);

        // Esperamos um erro 400 porque o payload do webhook não é completo,
        // mas um 200 ou 4xx significa que o middleware de assinatura passou.
        // Um 403 significaria que o middleware falhou.
        $this->assertNotEquals(403, $response->getStatusCode());
    }
}
