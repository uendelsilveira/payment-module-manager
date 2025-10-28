<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 28/10/2025 20:43:22
*/

namespace Us\PaymentModuleManager\Contracts;

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
}
