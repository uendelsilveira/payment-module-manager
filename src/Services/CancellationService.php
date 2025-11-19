<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 19/11/2025 02:30:00
*/

namespace UendelSilveira\PaymentModuleManager\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use UendelSilveira\PaymentModuleManager\Contracts\TransactionRepositoryInterface;
use UendelSilveira\PaymentModuleManager\DTOs\CancelPaymentResponse;
use UendelSilveira\PaymentModuleManager\Exceptions\InvalidRefundException;
use UendelSilveira\PaymentModuleManager\Exceptions\RefundProcessingException;
use UendelSilveira\PaymentModuleManager\Exceptions\TransactionNotFoundException;
use UendelSilveira\PaymentModuleManager\PaymentGatewayManager;
use UendelSilveira\PaymentModuleManager\Support\LogContext;

class CancellationService
{
    public function __construct(
        protected PaymentGatewayManager $gatewayManager,
        protected TransactionRepositoryInterface $transactionRepo,
        protected AuditLogger $auditLogger
    ) {}

    /**
     * Cancel a pending payment transaction.
     *
     * @param string      $transactionId The transaction ID (external_id)
     * @param string|null $reason        The reason for cancellation
     *
     * @throws TransactionNotFoundException
     * @throws InvalidRefundException
     * @throws RefundProcessingException
     */
    public function cancel(string $transactionId, ?string $reason = null): CancelPaymentResponse
    {
        $startTime = microtime(true);

        // Find transaction by external_id
        $transaction = $this->transactionRepo->findBy('external_id', $transactionId);

        if (! $transaction instanceof \UendelSilveira\PaymentModuleManager\Models\Transaction) {
            throw new TransactionNotFoundException(sprintf('Transaction %s not found', $transactionId));
        }

        $logContext = LogContext::create()
            ->withCorrelationId()
            ->withTransaction($transaction)
            ->withRequestId()
            ->with('cancellation_reason', $reason);

        Log::channel('payment')->info('Starting payment cancellation', $logContext->toArray());

        // Validate transaction status - only pending transactions can be cancelled
        if ($transaction->status !== 'pending') {
            throw new InvalidRefundException(
                'Only pending transactions can be cancelled. Current status: '.$transaction->status
            );
        }

        // Validate external_id exists
        if (empty($transaction->external_id)) {
            throw new InvalidRefundException('Transaction does not have an external_id. Cannot cancel payment.');
        }

        DB::beginTransaction();

        try {
            // Call gateway cancel API
            $gateway = $this->gatewayManager->gateway($transaction->gateway);
            $response = $gateway->cancelPayment($transaction->external_id);

            // Update transaction status to cancelled
            $metadata = (array) $transaction->metadata;
            $metadata['cancellation'] = [
                'reason' => $reason,
                'cancelled_at' => now()->toIso8601String(),
                'gateway_response' => $response->details,
            ];

            $this->transactionRepo->update($transaction->id, [
                'status' => 'cancelled',
                'metadata' => $metadata,
            ]);

            DB::commit();

            $transaction->refresh();

            // Log audit trail
            $correlationId = is_string($logContext->toArray()['correlation_id'] ?? null)
                ? $logContext->toArray()['correlation_id']
                : null;

            $this->auditLogger->logCancellation(
                $transaction,
                $reason,
                $correlationId,
                $response->details
            );

            $logContext->withTransaction($transaction)
                ->withDuration($startTime)
                ->with('cancel_data', $response->details);

            Log::channel('payment')->info('Payment cancelled successfully', $logContext->toArray());

            return $response;
        } catch (\Throwable $throwable) {
            DB::rollBack();

            $logContext->withError($throwable)
                ->withDuration($startTime);

            Log::channel('payment')->error('Payment cancellation failed', $logContext->toArray());

            if ($throwable instanceof InvalidRefundException || $throwable instanceof TransactionNotFoundException) {
                throw $throwable;
            }

            throw new RefundProcessingException($throwable->getMessage(), $throwable->getCode(), $throwable);
        }
    }
}
