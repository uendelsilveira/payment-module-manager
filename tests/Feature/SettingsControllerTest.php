<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 28/10/2025 20:43:21
*/

namespace UendelSilveira\PaymentModuleManager\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use UendelSilveira\PaymentModuleManager\Models\PaymentSetting;
use UendelSilveira\PaymentModuleManager\Tests\TestCase;

class SettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_save_and_get_mercado_pago_settings(): void
    {
        // 1. Salvar as configurações
        $settingsPayload = [
            'public_key' => 'test_public_key',
            'access_token' => 'test_access_token',
            'webhook_secret' => 'test_webhook_secret',
        ];

        $testResponse = $this->postJson(route('settings.mercadopago.save'), $settingsPayload);

        $testResponse->assertStatus(200)
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
                    'public_key_configured' => true,
                    'access_token_configured' => true,
                    'webhook_secret_configured' => true,
                ],
            ]);
    }

    public function test_it_can_update_existing_settings(): void
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

    public function test_it_redirects_to_mercadopago_connect_url(): void
    {
        Config::set('payment.gateways.mercadopago.client_id', 'TEST_CLIENT_ID');

        $testResponse = $this->get(route('connect.mercadopago.redirect'));

        $testResponse->assertStatus(302);
        // Verifica se é um redirecionamento
        $location = $testResponse->headers->get('location');
        $this->assertIsString($location);
        $this->assertStringContainsString('https://auth.mercadopago.com.br/authorization', $location);
        $this->assertStringContainsString('client_id=TEST_CLIENT_ID', $location);
    }

    public function test_it_handles_mercadopago_callback_and_saves_token(): void
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

        $testResponse = $this->get(route('connect.mercadopago.callback', ['code' => 'test_auth_code']));

        // Verifica se o redirecionamento de sucesso ocorreu
        $testResponse->assertRedirect('/');

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
