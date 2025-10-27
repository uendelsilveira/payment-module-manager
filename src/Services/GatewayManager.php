<?php

namespace Us\PaymentModuleManager\Services;

use Us\PaymentModuleManager\Contracts\PaymentGatewayInterface;
use Us\PaymentModuleManager\Enums\PaymentGateway;
use Us\PaymentModuleManager\Gateways\MercadoPagoStrategy;
use Us\PaymentModuleManager\Gateways\PagSeguroStrategy;
use Us\PaymentModuleManager\Gateways\PayPalStrategy;
use Us\PaymentModuleManager\Gateways\StripeStrategy;

class GatewayManager
{
    public function create(string $gateway): PaymentGatewayInterface
    {
        return match ($gateway) {
            PaymentGateway::MERCADOPAGO => new MercadoPagoStrategy(),
            PaymentGateway::PAGSEGURO => new PagSeguroStrategy(),
            PaymentGateway::PAYPAL => new PayPalStrategy(),
            PaymentGateway::STRIPE => new StripeStrategy(),
            default => throw new \InvalidArgumentException('Gateway de pagamento inválido.'),
        };
    }
}
