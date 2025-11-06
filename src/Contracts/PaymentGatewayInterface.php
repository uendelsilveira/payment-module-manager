<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 28/10/2025 20:43:21
*/

namespace UendelSilveira\PaymentModuleManager\Contracts;

interface PaymentGatewayInterface
{
    /**
     * Processa uma cobrança.
     *
     * @param array<string, mixed> $data Dados adicionais (ex: descrição, dados do cliente)
     *
     * @return array<string, mixed> Retorna os dados da transação da API externa
     */
    public function charge(float $amount, array $data): array;

    /**
     * Obtém os detalhes de um pagamento existente.
     *
     * @param string $externalPaymentId O ID do pagamento no gateway externo
     *
     * @return array<string, mixed> Retorna os dados da transação da API externa
     */
    public function getPayment(string $externalPaymentId): array;

    /**
     * Realiza o estorno total ou parcial de um pagamento.
     *
     * @param string $externalPaymentId O ID do pagamento no gateway externo
     * @param float|null $amount Valor a estornar (null = estorno total)
     *
     * @return array<string, mixed> Retorna os dados do estorno
     */
    public function refund(string $externalPaymentId, ?float $amount = null): array;

    /**
     * Cancela um pagamento pendente.
     *
     * @param string $externalPaymentId O ID do pagamento no gateway externo
     *
     * @return array<string, mixed> Retorna os dados do cancelamento
     */
    public function cancel(string $externalPaymentId): array;
}
