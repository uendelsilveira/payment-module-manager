<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 28/10/2025 20:43:21
*/

namespace UendelSilveira\PaymentModuleManager\Tests\Feature;

use UendelSilveira\PaymentModuleManager\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use UendelSilveira\PaymentModuleManager\Models\PaymentSetting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

class SettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_save_and_get_mercado_pago_settings()
    {
        // 1. Salvar as configurações
        $settingsPayload = [
            'public_key' => 'test_public_key',
            'access_token' => 'test_access_token',
            'webhook_secret' => 'test_webhook_secret',
        ];

        $saveResponse = $this->postJson(route('settings.mercadopago.save'), $settingsPayload);

        $saveResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Configurações salvas com sucesso.',
            ]);

        // 2. Verificar se as configurações foram salvas no banco de dados
        $this->assertDatabaseHas('payment_settings', [
            'key' => 'mercadopago_access_token',
            'value' => 'test_access_token',
        ]);

        // 3. Buscar as configurações salvas
        $getResponse = $this->getJson(route('settings.mercadopago.get'));

        $getResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'public_key' => 'test_public_key',
                    'access_token' => 'test_access_token',
                    'webhook_secret' => 'test_webhook_secret',
                ],
            ]);
    }

    public function test_it_can_update_existing_settings()
    {
        // Salva uma configuração inicial
        PaymentSetting::create([
            'key' => 'mercadopago_access_token',
            'value' => 'old_token',
        ]);

        // Envia a requisição para atualizar
        $updatePayload = [
            'access_token' => 'new_token',
        ];

        $this->postJson(route('settings.mercadopago.save'), $updatePayload);

        // Verifica se o valor foi atualizado no banco de dados
        $this->assertDatabaseHas('payment_settings', [
            'key' => 'mercadopago_access_token',
            'value' => 'new_token',
        ]);

        $this->assertDatabaseMissing('payment_settings', [
            'key' => 'mercadopago_access_token',
            'value' => 'old_token',
        ]);
    }

    public function test_it_redirects_to_mercadopago_connect_url()
    {
        Config::set('payment.gateways.mercadopago.client_id', 'TEST_CLIENT_ID');

        $response = $this->get(route('connect.mercadopago.redirect'));

        $response->assertStatus(302); // Verifica se é um redirecionamento
        $this->assertStringContainsString('https://auth.mercadopago.com.br/authorization', $response->headers->get('location'));
        $this->assertStringContainsString('client_id=TEST_CLIENT_ID', $response->headers->get('location'));
    }

    public function test_it_handles_mercadopago_callback_and_saves_token()
    {
        Config::set('payment.gateways.mercadopago.client_id', 'TEST_CLIENT_ID');
        Config::set('payment.gateways.mercadopago.client_secret', 'TEST_CLIENT_SECRET');

        // Mock da chamada HTTP para a API do Mercado Pago
        Http::fake([
            'https://api.mercadopago.com/oauth/token' => Http::response([
                'access_token' => 'new_access_token_from_oauth',
                'public_key' => 'new_public_key_from_oauth',
                'refresh_token' => 'new_refresh_token',
                'user_id' => 12345,
            ], 200),
        ]);

        $response = $this->get(route('connect.mercadopago.callback', ['code' => 'test_auth_code']));

        // Verifica se o redirecionamento de sucesso ocorreu
        $response->assertRedirect('/');

        // Verifica se as novas credenciais foram salvas no banco de dados
        $this->assertDatabaseHas('payment_settings', [
            'key' => 'mercadopago_access_token',
            'value' => 'new_access_token_from_oauth',
        ]);
        $this->assertDatabaseHas('payment_settings', [
            'key' => 'mercadopago_public_key',
            'value' => 'new_public_key_from_oauth',
        ]);
    }
}
