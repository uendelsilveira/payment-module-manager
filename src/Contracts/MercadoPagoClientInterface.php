<?php

namespace UendelSilveira\PaymentModuleManager\Contracts;

interface MercadoPagoClientInterface
{
    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function createPayment(float $amount, array $data): array;

    /**
     * @return array<string, mixed>
     */
    public function getPayment(string $paymentId): array;

    /**
     * @return array<int, object>
     */
    public function getPaymentMethods(): array;

    /**
     * @return array<string, mixed>
     */
    public function refundPayment(string $paymentId, ?float $amount = null): array;

    /**
     * @return array<string, mixed>
     */
    public function cancelPayment(string $paymentId): array;
}
