<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 19/11/2025 04:00:00
*/

namespace UendelSilveira\PaymentModuleManager\Services;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Request;
use UendelSilveira\PaymentModuleManager\Models\AuditLog;
use UendelSilveira\PaymentModuleManager\Models\Refund;
use UendelSilveira\PaymentModuleManager\Models\Transaction;

class AuditLogger
{
    /**
     * Log a refund operation.
     *
     * @param array<string, mixed> $additionalData
     */
    public function logRefund(
        Transaction $transaction,
        Refund $refund,
        ?string $correlationId = null,
        array $additionalData = []
    ): AuditLog {
        $previousStatus = $additionalData['previous_status'] ?? $transaction->status;

        return AuditLog::create([
            'transaction_id' => $transaction->id,
            'operation_type' => 'refund',
            'user_id' => $this->getCurrentUserId(),
            'user_type' => $this->getCurrentUserType(),
            'amount' => $refund->amount,
            'reason' => $refund->reason,
            'previous_status' => $previousStatus,
            'new_status' => $transaction->status,
            'gateway_response' => $refund->gateway_response,
            'ip_address' => Request::ip(),
            'correlation_id' => $correlationId ?? $this->generateCorrelationId(),
            'metadata' => array_merge([
                'refund_id' => $refund->id,
                'gateway_refund_id' => $refund->gateway_refund_id,
                'processed_at' => $refund->processed_at?->toIso8601String(),
            ], $additionalData),
        ]);
    }

    /**
     * Log a cancellation operation.
     *
     * @param array<string, mixed> $gatewayResponse
     * @param array<string, mixed> $additionalData
     */
    public function logCancellation(
        Transaction $transaction,
        ?string $reason = null,
        ?string $correlationId = null,
        array $gatewayResponse = [],
        array $additionalData = []
    ): AuditLog {
        $previousStatus = $additionalData['previous_status'] ?? 'pending';

        return AuditLog::create([
            'transaction_id' => $transaction->id,
            'operation_type' => 'cancellation',
            'user_id' => $this->getCurrentUserId(),
            'user_type' => $this->getCurrentUserType(),
            'amount' => null, // Cancellations don't have an amount
            'reason' => $reason,
            'previous_status' => $previousStatus,
            'new_status' => $transaction->status,
            'gateway_response' => $gatewayResponse,
            'ip_address' => Request::ip(),
            'correlation_id' => $correlationId ?? $this->generateCorrelationId(),
            'metadata' => array_merge([
                'cancelled_at' => now()->toIso8601String(),
            ], $additionalData),
        ]);
    }

    /**
     * Get audit trail for a transaction.
     *
     * @return EloquentCollection<int, AuditLog>
     */
    public function getAuditTrail(int $transactionId): EloquentCollection
    {
        /** @var EloquentCollection<int, AuditLog> $collection */
        $collection = AuditLog::where('transaction_id', $transactionId)
            ->orderBy('created_at', 'desc')
            ->get();

        return $collection;
    }

    /**
     * Get audit logs by correlation ID.
     *
     * @return EloquentCollection<int, AuditLog>
     */
    public function getByCorrelationId(string $correlationId): EloquentCollection
    {
        /** @var EloquentCollection<int, AuditLog> $collection */
        $collection = AuditLog::where('correlation_id', $correlationId)
            ->orderBy('created_at', 'asc')
            ->get();

        return $collection;
    }

    /**
     * Get current user ID (from auth or request).
     */
    protected function getCurrentUserId(): ?string
    {
        // Try to get authenticated user
        $factory = auth();

        if (method_exists($factory, 'check') && $factory->check()) {
            $userId = method_exists($factory, 'id') ? $factory->id() : null;

            return $userId ? (string) $userId : 'system';
        }

        // Try to get API key or user identifier from request headers
        $apiKey = Request::header('X-API-Key');

        if (is_string($apiKey)) {
            return 'api:'.substr($apiKey, 0, 8);
        }

        return 'system';
    }

    /**
     * Get current user type.
     */
    protected function getCurrentUserType(): string
    {
        $factory = auth();

        if (method_exists($factory, 'check') && $factory->check()) {
            return 'user';
        }

        if (Request::header('X-API-Key')) {
            return 'api';
        }

        return 'system';
    }

    /**
     * Generate a correlation ID for tracking.
     */
    protected function generateCorrelationId(): string
    {
        return uniqid('audit_', true);
    }
}
