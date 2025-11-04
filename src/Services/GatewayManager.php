<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 28/10/2025 20:43:22
*/

namespace UendelSilveira\PaymentModuleManager\Services;

use UendelSilveira\PaymentModuleManager\Contracts\PaymentGatewayInterface;
use UendelSilveira\PaymentModuleManager\Enums\PaymentGateway;
use UendelSilveira\PaymentModuleManager\Gateways\MercadoPagoStrategy;

class GatewayManager
{
    /**
     * Cria a instância do gateway de pagamento.
     */
    public function create(string $gateway): PaymentGatewayInterface
    {
        if ($gateway === PaymentGateway::MERCADOPAGO) {
            // Resolve a estratégia do container do Laravel para permitir mocking
            return app(MercadoPagoStrategy::class);
        }

        throw new \UendelSilveira\PaymentModuleManager\Exceptions\InvalidConfigurationException('O gateway de pagamento selecionado não é suportado.');
    }
}
