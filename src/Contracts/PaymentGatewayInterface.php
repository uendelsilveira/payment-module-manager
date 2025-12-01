<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 19/11/2025 14:24:12
*/

namespace UendelSilveira\PaymentModuleManager\Contracts;

use UendelSilveira\PaymentModuleManager\DTOs\CancelPaymentResponse;
use UendelSilveira\PaymentModuleManager\DTOs\ProcessPaymentResponse;
use UendelSilveira\PaymentModuleManager\DTOs\RefundPaymentResponse;
use UendelSilveira\PaymentModuleManager\Enums\PaymentStatus;
use UendelSilveira\PaymentModuleManager\Exceptions\PaymentFailedException;
use UendelSilveira\PaymentModuleManager\Exceptions\RefundFailedException;
use UendelSilveira\PaymentModuleManager\Exceptions\TransactionNotFoundException;
use UendelSilveira\PaymentModuleManager\Exceptions\WebhookProcessingException;

interface PaymentGatewayInterface
{
    /**
     * @param array<string, mixed> $data
     *
     * @throws PaymentFailedException
     */
    public function processPayment(array $data): ProcessPaymentResponse;

    /**
     * Retorna o status do pagamento no provedor.
     *
     * @throws TransactionNotFoundException
     */
    public function getPaymentStatus(string $transactionId): PaymentStatus;

    /**
     * @throws RefundFailedException
     * @throws TransactionNotFoundException
     */
    public function refundPayment(string $transactionId, ?float $amount = null): RefundPaymentResponse;

    /**
     * @throws TransactionNotFoundException
     */
    public function cancelPayment(string $transactionId): CancelPaymentResponse;

    /**
     * @param array<string, mixed> $payload
     *
     * @throws WebhookProcessingException
     *
     * @return array<string, mixed>
     */
    public function handleWebhook(array $payload): array;

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array;
}
