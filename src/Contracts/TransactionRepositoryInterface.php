<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 28/10/2025 20:43:21
*/

namespace UendelSilveira\PaymentModuleManager\Contracts;

use UendelSilveira\PaymentModuleManager\Models\Transaction;

interface TransactionRepositoryInterface
{
    /**
     * Cria uma nova transação no banco de dados.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): Transaction;
}
