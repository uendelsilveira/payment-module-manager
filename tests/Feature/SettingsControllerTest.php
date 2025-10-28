<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 28/10/2025 20:43:21
*/

namespace UendelSilveira\PaymentModuleManager\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use UendelSilveira\PaymentModuleManager\Models\PaymentSetting;
use UendelSilveira\PaymentModuleManager\Tests\TestCase;

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
}
