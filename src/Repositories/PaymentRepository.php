<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created at: 27/10/25
*/

namespace UendelSilveira\PaymentModuleManager\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Throwable;
use UendelSilveira\PaymentModuleManager\Exceptions\PaymentNotFoundException;
use UendelSilveira\PaymentModuleManager\Logger\PaymentLogger;
use UendelSilveira\PaymentModuleManager\Models\Transaction;

class PaymentRepository
{
    /**
     * @param array<string, mixed> $data
     *
     * @throws Throwable
     */
    public function create(array $data): Transaction
    {
        try {
            $transaction = Transaction::create($data);
            PaymentLogger::logPaymentCreated($transaction->toArray());

            return $transaction;
        } catch (Throwable $e) {
            PaymentLogger::logPaymentError('Failed to save payment', [
                'data' => $data,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws Throwable
     */
    public function update(int $id, array $data): bool
    {
        try {
            $transaction = $this->findById($id);

            if (! $transaction) {
                throw new PaymentNotFoundException("Payment {$id} not found");
            }

            $previousStatus = $transaction->status;
            $updated = $transaction->update($data);

            if ($updated && isset($data['status']) && $data['status'] !== $previousStatus) {
                $freshTransaction = $transaction->fresh();

                if ($freshTransaction) {
                    PaymentLogger::logPaymentUpdated($freshTransaction->toArray(), $previousStatus);
                }
            }

            return $updated;
        } catch (Throwable $e) {
            PaymentLogger::logPaymentError('Failed to update payment', [
                'payment_id' => $id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function findById(int $id): ?Transaction
    {
        return Transaction::find($id);
    }

    public function findByGatewayPaymentId(string $gateway, string $gatewayPaymentId): ?Transaction
    {
        return Transaction::where('gateway', $gateway)
            ->where('external_id', $gatewayPaymentId)
            ->first();
    }

    /**
     * @return Collection<int, Transaction>
     */
    public function findByStatus(string $status, int $limit = 100): Collection
    {
        /** @var Collection<int, Transaction> $result */
        $result = Transaction::where('status', $status)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return $result;
    }

    /**
     * @return Collection<int, Transaction>
     */
    public function findExpired(): Collection
    {
        /** @var Collection<int, Transaction> $result */
        $result = Transaction::where('status', 'pending')
            ->where('expires_at', '<', now())
            ->get();

        return $result;
    }

    /**
     * @return Collection<int, Transaction>
     */
    public function getFailedToReprocess(): Collection
    {
        /** @var Collection<int, Transaction> $result */
        $result = Transaction::where('status', 'failed')
            ->where('retries_count', '<', 3) // Exemplo, idealmente viria de config
            ->get();

        return $result;
    }
}
