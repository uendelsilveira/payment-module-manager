<?php

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
