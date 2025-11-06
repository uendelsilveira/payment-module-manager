<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created at: 27/10/25
*/

namespace UendelSilveira\PaymentModuleManager\Contracts;

interface PaymentProviderInterface
{
    /**
     * @param array<string, mixed> $data
     */
    public function processPayment(array $data): bool;

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function createPayment(float $amount, string $currency, array $options = []): array;

    public function getPaymentStatus(string $paymentId): string;

    /**
     * @return array<string, mixed>
     */
    public function refundPayment(string $paymentId, float $amount): array;

    /**
     * @return array<string, mixed>
     */
    public function cancelPayment(string $paymentId): array;
}
