<?php

namespace UendelSilveira\PaymentModuleManager\Contracts;

use UendelSilveira\PaymentModuleManager\Enums\PaymentStatus;

interface PaymentGatewayInterface
{
    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function processPayment(array $data): array;

    /**
     * Retorna o status do pagamento no provedor.
     */
    public function getPaymentStatus(string $transactionId): PaymentStatus;

    /**
     * @return array<string, mixed>
     */
    public function refundPayment(string $transactionId, ?float $amount = null): array;

    /**
     * @return array<string, mixed>
     */
    public function cancelPayment(string $transactionId): array;

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function createWebhook(array $data): array;

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function processWebhook(array $data): array;

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array;
}
