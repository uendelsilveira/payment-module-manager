<?php

namespace Us\PaymentModuleManager\Contracts;

use Us\PaymentModuleManager\Models\Transaction;

interface TransactionRepositoryInterface
{
    /**
     * Cria uma nova transação no banco de dados.
     *
     * @param array $data
     * @return Transaction
     */
    public function create(array $data): Transaction;
}
