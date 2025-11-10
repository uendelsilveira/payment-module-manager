<?php

namespace UendelSilveira\PaymentModuleManager\Tests\Unit;

use UendelSilveira\PaymentModuleManager\Gateways\MercadoPagoGateway;
use UendelSilveira\PaymentModuleManager\Services\GatewayManager;
use UendelSilveira\PaymentModuleManager\Tests\TestCase;

class GatewayManagerTest extends TestCase
{
    private GatewayManager $gatewayManager;

    protected function setUp(): void
    {
        parent::setUp();

        // Obtém a instância do GatewayManager a partir do container de serviços do Laravel
        // Isso garante que suas dependências (como o Container) sejam injetadas corretamente.
        if ($this->app !== null) {
            $this->gatewayManager = $this->app->make(GatewayManager::class);
        }
    }

    public function test_it_creates_mercadopago_driver_correctly(): void
    {
        // Act
        $gateway = $this->gatewayManager->create('mercadopago');

        // Assert
        $this->assertInstanceOf(MercadoPagoGateway::class, $gateway);
    }

    public function test_it_throws_exception_for_invalid_gateway(): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Gateway [invalid-gateway] não é suportado.');

        // Act
        $this->gatewayManager->create('invalid-gateway');
    }
}
