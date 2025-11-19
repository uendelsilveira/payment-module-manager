<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 19/11/2025 14:24:12
*/

namespace UendelSilveira\PaymentModuleManager\Repositories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use UendelSilveira\PaymentModuleManager\Contracts\TransactionRepositoryInterface;
use UendelSilveira\PaymentModuleManager\Enums\PaymentStatus;
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

    /**
     * Encontra uma transação pelo seu ID.
     */
    public function find(int $id): ?Transaction
    {
        return Transaction::find($id);
    }

    /**
     * Encontra uma transação por uma coluna e valor específicos.
     *
     * @param mixed $value
     */
    public function findBy(string $column, $value): ?Transaction
    {
        return Transaction::where($column, $value)->first();
    }

    /**
     * Atualiza uma transação.
     *
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        $transaction = $this->find($id);

        if ($transaction instanceof \UendelSilveira\PaymentModuleManager\Models\Transaction) {
            return $transaction->update($data);
        }

        return false;
    }

    /**
     * Retorna transações falhadas que podem ser reprocessadas.
     *
     * @return Collection<int, Transaction>
     */
    public function getFailedToReprocess(): Collection
    {
        /** @var int $maxAttempts */
        $maxAttempts = config('payment.retry.max_attempts', 3);
        /** @var int $retryInterval */
        $retryInterval = config('payment.retry.retry_interval_minutes', 5);

        return Transaction::where('status', PaymentStatus::FAILED->value)
            ->where('retries_count', '<', $maxAttempts)
            ->where(function ($query) use ($retryInterval): void {
                $query->whereNull('last_attempt_at')
                    ->orWhere('last_attempt_at', '<', Carbon::now()->subMinutes($retryInterval));
            })
            ->get();
    }
}
