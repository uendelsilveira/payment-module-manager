<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 19/11/2025 14:24:12
*/

namespace UendelSilveira\PaymentModuleManager\Contracts;

use Illuminate\Http\Request;

interface GatewayInterface
{
    // Métodos de Configuração
    public function getName(): string;

    /**
     * @return array<string, mixed>
     */
    public function getSettings(): array;

    /**
     * @param array<string, mixed> $data
     */
    public function saveSettings(array $data): void;

    public function getAuthorizationUrl(): string;

    public function handleCallback(Request $request): void;

    // Métodos de Pagamento
    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function charge(float $amount, array $data): array;

    /**
     * @return array<string, mixed>
     */
    public function getPayment(string $externalPaymentId): array;

    /**
     * @return array<string, mixed>
     */
    public function refund(string $externalPaymentId, ?float $amount = null): array;

    /**
     * @return array<string, mixed>
     */
    public function cancel(string $externalPaymentId): array;
}
