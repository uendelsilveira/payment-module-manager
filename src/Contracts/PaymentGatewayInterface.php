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
     * @param array $data Dados adicionais (ex: descrição, dados do cliente)
     *
     * @return array Retorna os dados da transação da API externa
     */
    public function charge(float $amount, array $data): array;

    /**
     * Obtém os detalhes de um pagamento existente.
     *
     * @param string $externalPaymentId O ID do pagamento no gateway externo
     *
     * @return array Retorna os dados da transação da API externa
     */
    public function getPayment(string $externalPaymentId): array;
}
