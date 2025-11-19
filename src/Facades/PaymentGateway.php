<?php

namespace UendelSilveira\PaymentModuleManager\Facades;

use Illuminate\Support\Facades\Facade;
use UendelSilveira\PaymentModuleManager\PaymentGatewayManager;

/**
 * @method static \UendelSilveira\PaymentModuleManager\Contracts\PaymentGatewayInterface gateway(string $name = null)
 * @method static \UendelSilveira\PaymentModuleManager\Contracts\PaymentGatewayInterface driver(string $driver = null)
 *
 * @see \UendelSilveira\PaymentModuleManager\PaymentGatewayManager
 */
class PaymentGateway extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return PaymentGatewayManager::class;
    }
}
