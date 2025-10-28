<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created at: 27/10/25
*/

namespace UendelSilveira\PaymentModuleManager\Services;

use UendelSilveira\PaymentModuleManager\Contracts\PaymentProviderInterface;

abstract class MercadoPagoService implements PaymentProviderInterface
{
    public function processPayment(array $data): bool
    {
        // Aqui você implementa a integração com o Mercado Pago
        return true;
    }

    public function refundPayment(string $paymentId, float $amount): array
    {
        return ['refunded' => true];
    }
}
