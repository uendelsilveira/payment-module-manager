<?php

namespace UendelSilveira\PaymentModuleManager\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Mockery;
use UendelSilveira\PaymentModuleManager\Contracts\GatewayInterface;
use UendelSilveira\PaymentModuleManager\Services\GatewayManager;
use UendelSilveira\PaymentModuleManager\Tests\TestCase;

class SettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    private \UendelSilveira\PaymentModuleManager\Contracts\GatewayInterface|\Mockery\MockInterface $gatewayMock;

    protected function setUp(): void
    {
        parent::setUp();

        // Cria um mock da nossa GatewayInterface
        $this->gatewayMock = Mockery::mock(GatewayInterface::class);

        // Cria um mock do GatewayManager
        $managerMock = Mockery::mock(GatewayManager::class);
        // Configura o manager para retornar nosso mock de gateway quando `create('mercadopago')` for chamado
        $managerMock->shouldReceive('create')->with('mercadopago')->andReturn($this->gatewayMock);

        // Injeta o mock do manager no container de serviços do Laravel
        $this->app->instance(GatewayManager::class, $managerMock);
    }

    public function test_it_can_get_gateway_settings(): void
    {
        // Arrange: O que esperamos que o gateway retorne
        $expectedSettings = [
            'public_key' => 'TEST_KEY_MASKED',
            'public_key_configured' => true,
        ];
        $this->gatewayMock->shouldReceive('getSettings')->once()->andReturn($expectedSettings);

        // Act: Chama a rota do controller
        $testResponse = $this->getJson(route('settings.gateway.get', ['gateway' => 'mercadopago']));

        // Assert: Verifica se a resposta está correta
        $testResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => $expectedSettings,
            ]);
    }

    public function test_it_can_save_gateway_settings(): void
    {
        // Arrange: Os dados que enviaremos
        $settingsPayload = [
            'public_key' => 'test_public_key',
            'access_token' => 'test_access_token',
        ];

        // Configura o mock para esperar uma chamada ao método `saveSettings` com os dados corretos
        $this->gatewayMock->shouldReceive('saveSettings')->once()->with($settingsPayload);

        // Act: Chama a rota
        $testResponse = $this->postJson(route('settings.gateway.save', ['gateway' => 'mercadopago']), $settingsPayload);

        // Assert: Verifica se a resposta foi de sucesso
        $testResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Configurações salvas com sucesso.',
            ]);
    }

    public function test_it_redirects_to_gateway_connect_url(): void
    {
        // Arrange: A URL que esperamos que o gateway nos forneça
        $expectedUrl = 'https://auth.mercadopago.com.br/authorization?client_id=TEST_CLIENT_ID';
        $this->gatewayMock->shouldReceive('getAuthorizationUrl')->once()->andReturn($expectedUrl);

        // Act: Chama a rota
        $testResponse = $this->get(route('connect.gateway.redirect', ['gateway' => 'mercadopago']));

        // Assert: Verifica se o redirecionamento ocorreu para a URL correta
        $testResponse->assertStatus(302);
        $testResponse->assertRedirect($expectedUrl);
    }

    public function test_it_handles_gateway_callback(): void
    {
        // Arrange: Configura o mock para esperar uma chamada ao `handleCallback`
        // Usamos um closure para poder fazer asserções no objeto Request que é passado
        $this->gatewayMock->shouldReceive('handleCallback')->once()->with(Mockery::on(function ($request): true {
            $this->assertInstanceOf(Request::class, $request);
            $this->assertEquals('test_auth_code', $request->input('code'));

            return true;
        }));

        // Act: Chama a rota de callback
        $testResponse = $this->get(route('connect.gateway.callback', ['gateway' => 'mercadopago', 'code' => 'test_auth_code']));

        // Assert: Verifica se o redirecionamento de sucesso ocorreu
        $testResponse->assertRedirect('/');
        $testResponse->assertSessionHas('status', 'Conta do gateway conectada com sucesso!');
    }

    public function test_it_handles_invalid_gateway(): void
    {
        // Arrange: Recria o mock do manager para lançar uma exceção quando um gateway inválido for solicitado
        $managerMock = Mockery::mock(GatewayManager::class);
        $managerMock->shouldReceive('create')->with('invalid-gateway')->andThrow(new \InvalidArgumentException('Gateway [invalid-gateway] não é suportado.'));
        $this->app->instance(GatewayManager::class, $managerMock);

        // Act: Chama a rota com o gateway inválido
        $testResponse = $this->getJson(route('settings.gateway.get', ['gateway' => 'invalid-gateway']));

        // Assert: Verifica se a resposta é um 404 Not Found
        $testResponse->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Gateway [invalid-gateway] não é suportado.',
            ]);
    }
}
