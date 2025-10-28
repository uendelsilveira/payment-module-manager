<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 28/10/2025 20:43:22
*/

namespace Us\PaymentModuleManager\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Us\PaymentModuleManager\Models\Transaction processPayment(array $data)
 *
 * @see \Us\PaymentModuleManager\Services\PaymentService
 */
class Payment extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return \Us\PaymentModuleManager\Services\PaymentService::class;
    }
}
