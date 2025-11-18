<?php

namespace UendelSilveira\PaymentModuleManager\Contracts;

use Illuminate\Database\Eloquent\Collection;
use UendelSilveira\PaymentModuleManager\Models\Transaction;

interface TransactionRepositoryInterface
{
    /**
     * Cria uma nova transação no banco de dados.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): Transaction;

    /**
     * Encontra uma transação pelo seu ID.
     */
    public function find(int $id): ?Transaction;

    /**
     * Encontra uma transação por uma coluna e valor específicos.
     *
     * @param mixed $value
     */
    public function findBy(string $column, $value): ?Transaction;

    /**
     * Atualiza uma transação.
     *
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): bool;

    /**
     * Retorna transações falhadas que podem ser reprocessadas.
     *
     * @return Collection<int, Transaction>
     */
    public function getFailedToReprocess(): Collection;
}
