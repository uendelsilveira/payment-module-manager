<?php

namespace Us\PaymentModuleManager\Tests\Unit;

use Us\PaymentModuleManager\Enums\PaymentGateway;
use Us\PaymentModuleManager\Gateways\MercadoPagoStrategy;
use Us\PaymentModuleManager\Services\GatewayManager;
use PHPUnit\Framework\TestCase;

class GatewayManagerTest extends TestCase
{
    protected GatewayManager $gatewayManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gatewayManager = new GatewayManager();
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
