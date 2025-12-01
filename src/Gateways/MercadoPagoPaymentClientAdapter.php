<?php

namespace UendelSilveira\PaymentModuleManager\Gateways;

use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Resources\Payment;
use UendelSilveira\PaymentModuleManager\Contracts\MercadoPagoPaymentClientInterface;

class MercadoPagoPaymentClientAdapter implements MercadoPagoPaymentClientInterface
{
    protected PaymentClient $paymentClient;

    public function __construct(?PaymentClient $paymentClient = null)
    {
        $this->paymentClient = $paymentClient ?? new PaymentClient;
    }

    /**
     * @param array<string, mixed> $paymentData
     */
    public function create(array $paymentData): Payment
    {
        return $this->paymentClient->create($paymentData);
    }

    public function get(int $id): Payment
    {
        return $this->paymentClient->get($id);
    }

    public function cancel(int $id): Payment
    {
        return $this->paymentClient->cancel($id);
    }
}
