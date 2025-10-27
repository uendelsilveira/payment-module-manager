<?php

namespace Us\PaymentModuleManager\Services;

use Us\PaymentModuleManager\Contracts\PaymentGatewayInterface;
use Us\PaymentModuleManager\Enums\PaymentGateway;
use Us\PaymentModuleManager\Gateways\MercadoPagoStrategy;

class GatewayManager
{
    /**
     * Cria a instância do gateway de pagamento.
     *
     * @param string $gateway
     * @return PaymentGatewayInterface
     */
    public function create(string $gateway): PaymentGatewayInterface
    {
        if ($gateway === PaymentGateway::MERCADOPAGO) {
            // Resolve a estratégia do container do Laravel para permitir mocking
            return app(MercadoPagoStrategy::class);
        }

        throw new \InvalidArgumentException('O gateway de pagamento selecionado não é suportado.');
    }
}
