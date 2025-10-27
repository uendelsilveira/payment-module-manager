<?php

namespace Us\PaymentModuleManager\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Us\PaymentModuleManager\Models\Transaction processPayment(array $data)
 * @see \Us\PaymentModuleManager\Services\PaymentService
 */
class Payment extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return \Us\PaymentModuleManager\Services\PaymentService::class;
    }
}
