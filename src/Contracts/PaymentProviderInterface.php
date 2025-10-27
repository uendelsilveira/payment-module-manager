<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created at: 27/10/25
*/

namespace Us\PaymentModuleManager\Contracts;

interface PaymentProviderInterface
{
    public function processPayment(array $data): bool;

    public function createPayment(float $amount, string $currency, array $options = []): array;

    public function getPaymentStatus(string $paymentId): string;

    public function refundPayment(string $paymentId, float $amount): array;

    public function cancelPayment(string $paymentId): array;
}
