<?php

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

    /**
     * @param array<string, mixed> $data
     */
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
     * @param array<string, mixed> $data
     */
    public function createWebhook(array $data): array
    {
        throw new NotImplementedException('Stripe create webhook is not implemented.');
    }

    /**
     * @param array<string, mixed> $data
     */
    public function processWebhook(array $data): array
    {
        throw new NotImplementedException('Stripe process webhook is not implemented.');
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}
