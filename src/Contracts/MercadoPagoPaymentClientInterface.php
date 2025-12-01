<?php

namespace UendelSilveira\PaymentModuleManager\Contracts;

use MercadoPago\Resources\Payment;

interface MercadoPagoPaymentClientInterface
{
    /**
     * @param array<string, mixed> $paymentData
     */
    public function create(array $paymentData): Payment;

    public function get(int $id): Payment;

    public function cancel(int $id): Payment;
}
