<?php

namespace UendelSilveira\PaymentModuleManager\Gateways;

use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Exceptions\MPApiException;
use UendelSilveira\PaymentModuleManager\Contracts\MercadoPagoClientInterface;

class MercadoPagoClientAdapter implements MercadoPagoClientInterface
{
    public function __construct(protected PaymentClient $paymentClient) {}

    /**
     * @param array<string, mixed> $request
     *
     * @throws MPApiException
     */
    public function create(array $request): object
    {
        return $this->paymentClient->create($request);
    }

    /**
     * @throws MPApiException
     */
    public function get(int $id): object
    {
        return $this->paymentClient->get($id);
    }

    /**
     * @throws MPApiException
     */
    public function cancel(int $id): object
    {
        return $this->paymentClient->cancel($id);
    }
}
