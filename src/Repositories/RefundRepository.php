<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 19/11/2025 01:30:00
*/

namespace UendelSilveira\PaymentModuleManager\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use UendelSilveira\PaymentModuleManager\Contracts\RefundRepositoryInterface;
use UendelSilveira\PaymentModuleManager\Models\Refund;

class RefundRepository implements RefundRepositoryInterface
{
    /**
     * Create a new refund record.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): Refund
    {
        return Refund::create($data);
    }

    /**
     * Find a refund by ID.
     */
    public function find(int $id): ?Refund
    {
        return Refund::find($id);
    }

    /**
     * Find a refund by gateway refund ID.
     */
    public function findByGatewayRefundId(string $gatewayRefundId): ?Refund
    {
        return Refund::where('gateway_refund_id', $gatewayRefundId)->first();
    }

    /**
     * Get total refunded amount for a transaction (only completed refunds).
     */
    public function getTotalRefunded(int $transactionId): float
    {
        $sumAmount = Refund::where('transaction_id', $transactionId)
            ->where('status', 'completed')
            ->sum('amount');

        /** @var float|int $sumAmount */
        $sumAmount = is_numeric($sumAmount) ? $sumAmount : 0.0;

        return (float) $sumAmount;
    }

    /**
     * Get refund history for a transaction with pagination.
     *
     * @return LengthAwarePaginator<int, Refund>
     */
    public function getRefundHistory(int $transactionId, int $perPage = 15): LengthAwarePaginator
    {
        return Refund::where('transaction_id', $transactionId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get all refunds for a transaction.
     *
     * @return Collection<int, Refund>
     */
    public function getRefundsByTransaction(int $transactionId): Collection
    {
        /** @var Collection<int, Refund> $refunds */
        $refunds = Refund::where('transaction_id', $transactionId)
            ->orderBy('created_at', 'desc')
            ->get();

        return $refunds;
    }
}
