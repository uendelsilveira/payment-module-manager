<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 19/11/2025 01:30:00
*/

namespace UendelSilveira\PaymentModuleManager\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use UendelSilveira\PaymentModuleManager\Models\Refund;

interface RefundRepositoryInterface
{
    /**
     * Create a new refund record.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): Refund;

    /**
     * Find a refund by ID.
     */
    public function find(int $id): ?Refund;

    /**
     * Find a refund by gateway refund ID.
     */
    public function findByGatewayRefundId(string $gatewayRefundId): ?Refund;

    /**
     * Get total refunded amount for a transaction (only completed refunds).
     */
    public function getTotalRefunded(int $transactionId): float;

    /**
     * Get refund history for a transaction with pagination.
     *
     * @return LengthAwarePaginator<int, Refund>
     */
    public function getRefundHistory(int $transactionId, int $perPage = 15): LengthAwarePaginator;

    /**
     * Get all refunds for a transaction.
     *
     * @return Collection<int, Refund>
     */
    public function getRefundsByTransaction(int $transactionId): Collection;
}
