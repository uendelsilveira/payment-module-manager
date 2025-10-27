<?php

namespace Us\PaymentModuleManager\Repositories;

use Us\PaymentModuleManager\Contracts\TransactionRepositoryInterface;
use Us\PaymentModuleManager\Models\Transaction;

class TransactionRepository implements TransactionRepositoryInterface
{
    /**
     * Cria uma nova transação no banco de dados.
     *
     * @param array $data
     * @return Transaction
     */
    public function create(array $data): Transaction
    {
        return Transaction::create($data);
    }
}
