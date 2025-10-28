<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 28/10/2025 20:43:21
*/

namespace Us\PaymentModuleManager\Tests\Unit;

use Mockery;
use Us\PaymentModuleManager\Enums\PaymentGateway;
use Us\PaymentModuleManager\Gateways\MercadoPagoStrategy;
use Us\PaymentModuleManager\Services\GatewayManager; // Alterado para estender o TestCase do pacote
use Us\PaymentModuleManager\Tests\TestCase;

class GatewayManagerTest extends TestCase
{
    protected GatewayManager $gatewayManager;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock do MercadoPagoStrategy para evitar que seu construtor real seja chamado
        // e cause o erro de Facade, já que estamos testando o GatewayManager, não a Strategy.
        $this->mock(MercadoPagoStrategy::class, function ($mock) {
            $mock->shouldReceive('__construct')->andReturnNull(); // Mocka o construtor
            $mock->shouldReceive('charge')->andReturn([]); // Mocka o método charge se for chamado
        });

        $this->gatewayManager = new GatewayManager;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_creates_mercadopago_strategy_correctly()
    {
        // Aqui, o GatewayManager tentará criar uma MercadoPagoStrategy.
        // Como MercadoPagoStrategy está mockado, ele retornará a instância mockada.
        $strategy = $this->gatewayManager->create(PaymentGateway::MERCADOPAGO);
        $this->assertInstanceOf(MercadoPagoStrategy::class, $strategy);
    }

    public function test_it_throws_exception_for_invalid_gateway()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->gatewayManager->create('invalid-gateway');
    }
}
