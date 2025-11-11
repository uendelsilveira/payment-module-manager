<?php

namespace UendelSilveira\PaymentModuleManager\Gateways;

use UendelSilveira\PaymentModuleManager\Contracts\PaymentGatewayInterface;
use UendelSilveira\PaymentModuleManager\Enums\PaymentStatus;

class MercadoPagoGateway implements PaymentGatewayInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(protected array $config = [])
    {
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function processPayment(array $data): array
    {
        // TODO: integrar SDK do Mercado Pago aqui.
        // Resposta mínima compatível com PaymentService
        return [
            'transaction_id' => $data['external_reference'] ?? uniqid('mp_', true),
            'status' => PaymentStatus::PENDING,
            'provider' => 'mercadopago',
        ];
    }

    public function getPaymentStatus(string $transactionId): PaymentStatus
    {
        // TODO: consultar status via API do Mercado Pago
        return PaymentStatus::PENDING;
    }

    /**
     * @return array<string, mixed>
     */
    public function refundPayment(string $transactionId, ?float $amount = null): array
    {
        // TODO: chamar API de estorno do Mercado Pago
        return [
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'status' => 'refunded_request',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function cancelPayment(string $transactionId): array
    {
        // TODO: chamar API de cancelamento do Mercado Pago
        return [
            'transaction_id' => $transactionId,
            'status' => 'cancel_requested',
        ];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function createWebhook(array $data): array
    {
        // TODO: criação/config de webhook se aplicável
        return [
            'success' => true,
        ];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function processWebhook(array $data): array
    {
        // TODO: processar webhook do Mercado Pago
        return [
            'received' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}
