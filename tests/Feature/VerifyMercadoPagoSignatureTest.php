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

    public function test_it_aborts_if_signature_header_is_missing(): void
    {
        $testResponse = $this->postJson(route('mercadopago.webhook'), []);

        $testResponse->assertStatus(403);
    }

    public function test_it_aborts_if_signature_is_invalid(): void
    {
        $payload = ['data' => ['id' => '12345']];

        $testResponse = $this->withHeaders([
            'x-signature' => 'ts=12345,v1=invalid_signature',
        ])->postJson(route('mercadopago.webhook'), $payload);

        $testResponse->assertStatus(403);
    }

    public function test_it_allows_request_with_valid_signature(): void
    {
        // Mock da dependência do controller para evitar chamadas reais à API
        $this->instance(MercadoPagoClientInterface::class, Mockery::mock(MercadoPagoClientInterface::class, function ($mock): void {
            // O método getPayment não será chamado neste teste, mas é bom ter um mock para ele
            $mock->shouldReceive('getPayment')->andReturn((object) []);
        }));

        $payload = ['data' => ['id' => '12345']];
        $ts = time();
        $secret = Config::get('payment.gateways.mercadopago.webhook_secret');

        $secretStr = is_string($secret) ? $secret : '';
        $manifest = sprintf('id:%s;request-id:%d;ts:%d;', $payload['data']['id'], $ts, $ts).json_encode($payload);
        $signature = hash_hmac('sha256', $manifest, $secretStr);

        $testResponse = $this->withHeaders([
            'x-signature' => sprintf('ts=%d,v1=%s', $ts, $signature),
        ])->postJson(route('mercadopago.webhook'), $payload);

        // Esperamos um erro 400 porque o payload do webhook não é completo,
        // mas um 200 ou 4xx significa que o middleware de assinatura passou.
        // Um 403 significaria que o middleware falhou.
        $this->assertNotEquals(403, $testResponse->getStatusCode());
    }
}
