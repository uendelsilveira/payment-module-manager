<?php

namespace UendelSilveira\PaymentModuleManager\Services;

use Illuminate\Contracts\Container\Container;
use UendelSilveira\PaymentModuleManager\Contracts\GatewayInterface;
use UendelSilveira\PaymentModuleManager\Gateways\MercadoPagoGateway;

class GatewayManager
{
    public function __construct(protected Container $container) {}

    /**
     * @throws \InvalidArgumentException
     */
    public function create(string $gateway): GatewayInterface
    {
        $method = 'create'.ucfirst($gateway).'Driver';

        if (method_exists($this, $method)) {
            return $this->{$method}();
        }

        throw new \InvalidArgumentException(sprintf('Gateway [%s] não é suportado.', $gateway));
    }

    protected function createMercadopagoDriver(): GatewayInterface
    {
        return $this->container->make(MercadoPagoGateway::class);
    }
}
