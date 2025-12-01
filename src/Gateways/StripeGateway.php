<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 19/11/2025 14:24:12
*/

namespace UendelSilveira\PaymentModuleManager\Gateways;

use UendelSilveira\PaymentModuleManager\Contracts\PaymentGatewayInterface;
use UendelSilveira\PaymentModuleManager\DTOs\CancelPaymentResponse;
use UendelSilveira\PaymentModuleManager\DTOs\ProcessPaymentResponse;
use UendelSilveira\PaymentModuleManager\DTOs\RefundPaymentResponse;
use UendelSilveira\PaymentModuleManager\Enums\PaymentStatus;
use UendelSilveira\PaymentModuleManager\Exceptions\NotImplementedException;

class StripeGateway implements PaymentGatewayInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(protected array $config = []) {}

    public function processPayment(array $data): ProcessPaymentResponse
    {
        throw new NotImplementedException('Stripe payment processing is not implemented.');
    }

    public function getPaymentStatus(string $transactionId): PaymentStatus
    {
        throw new NotImplementedException('Stripe get payment status is not implemented.');
    }

    public function refundPayment(string $transactionId, ?float $amount = null): RefundPaymentResponse
    {
        throw new NotImplementedException('Stripe refund payment is not implemented.');
    }

    public function cancelPayment(string $transactionId): CancelPaymentResponse
    {
        throw new NotImplementedException('Stripe cancel payment is not implemented.');
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function handleWebhook(array $payload): array
    {
        throw new NotImplementedException('Stripe handle webhook is not implemented.');
    }

    public function getConfig(): array
    {
        return $this->config;
    }
}
