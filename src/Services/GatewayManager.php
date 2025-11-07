<?php

namespace UendelSilveira\PaymentModuleManager\Services;

use UendelSilveira\PaymentModuleManager\Contracts\PaymentGatewayInterface;
use UendelSilveira\PaymentModuleManager\Enums\PaymentGateway;
use UendelSilveira\PaymentModuleManager\Exceptions\InvalidConfigurationException;
use UendelSilveira\PaymentModuleManager\Gateways\MercadoPagoStrategy;

class GatewayManager
{
    /**
     * Cria uma instância do gateway de pagamento.
     *
     * @throws InvalidConfigurationException
     */
    public function create(string $gateway): PaymentGatewayInterface
    {
        if ($gateway === PaymentGateway::MERCADOPAGO || $gateway === 'mercadopago') {
            return app(MercadoPagoStrategy::class);
        }

        throw new InvalidConfigurationException("O gateway '{$gateway}' não é suportado.");
    }
}
