<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 28/10/2025 20:43:21
*/

namespace UendelSilveira\PaymentModuleManager\Tests\Unit;

use Mockery;
use UendelSilveira\PaymentModuleManager\Enums\PaymentGateway;
use UendelSilveira\PaymentModuleManager\Gateways\MercadoPagoStrategy;
use UendelSilveira\PaymentModuleManager\Services\GatewayManager;
use UendelSilveira\PaymentModuleManager\Tests\TestCase;

class GatewayManagerTest extends TestCase
{
    protected GatewayManager $gatewayManager;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock do MercadoPagoStrategy para evitar chamadas reais
        $this->mock(MercadoPagoStrategy::class, function ($mock) {
            $mock->shouldReceive('charge')->andReturn([
                'id' => 'mocked_id',
                'status' => 'approved',
                'transaction_amount' => 100,
                'metadata' => [],
            ]);
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
        $strategy = $this->gatewayManager->create(PaymentGateway::MERCADOPAGO);
        $this->assertInstanceOf(MercadoPagoStrategy::class, $strategy);
    }

    public function test_it_throws_exception_for_invalid_gateway()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->gatewayManager->create('invalid-gateway');
    }
}
