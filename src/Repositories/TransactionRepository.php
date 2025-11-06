<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 28/10/2025 20:43:22
*/

namespace UendelSilveira\PaymentModuleManager\Repositories;

use UendelSilveira\PaymentModuleManager\Contracts\TransactionRepositoryInterface;
use UendelSilveira\PaymentModuleManager\Models\Transaction;

class TransactionRepository implements TransactionRepositoryInterface
{
    /**
     * Cria uma nova transação no banco de dados.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): Transaction
    {
        return Transaction::create($data);
    }
}
