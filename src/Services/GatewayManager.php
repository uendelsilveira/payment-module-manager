<?php

namespace Us\PaymentModuleManager\Services;

use Us\PaymentModuleManager\Enums\PaymentGateway;
use Us\PaymentModuleManager\Gateways\Contracts\PaymentGateway as PaymentGatewayContract;
use Us\PaymentModuleManager\Gateways\MercadoPagoStrategy;

class GatewayManager
{
    public function create(string $gateway): PaymentGatewayContract
    {
        return match ($gateway) {
            PaymentGateway::MERCADOPAGO => new MercadoPagoStrategy,
            default => throw new \InvalidArgumentException('Gateway de pagamento inválido.'),
        };
    }
}
