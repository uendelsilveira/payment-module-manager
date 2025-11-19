<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 19/11/2025 02:00:00
*/

namespace UendelSilveira\PaymentModuleManager\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use UendelSilveira\PaymentModuleManager\Contracts\RefundRepositoryInterface;
use UendelSilveira\PaymentModuleManager\Contracts\TransactionRepositoryInterface;
use UendelSilveira\PaymentModuleManager\DTOs\RefundPaymentResponse;
use UendelSilveira\PaymentModuleManager\Events\PaymentRefunded;
use UendelSilveira\PaymentModuleManager\Exceptions\InvalidRefundException;
use UendelSilveira\PaymentModuleManager\Exceptions\RefundProcessingException;
use UendelSilveira\PaymentModuleManager\Exceptions\TransactionNotFoundException;
use UendelSilveira\PaymentModuleManager\Models\Refund;
use UendelSilveira\PaymentModuleManager\PaymentGatewayManager;
use UendelSilveira\PaymentModuleManager\Support\LogContext;
use UendelSilveira\PaymentModuleManager\Validators\RefundEligibilityValidator;

class RefundService
{
    public function __construct(
        protected PaymentGatewayManager $gatewayManager,
        protected TransactionRepositoryInterface $transactionRepo,
        protected RefundRepositoryInterface $refundRepo,
        protected RefundEligibilityValidator $eligibilityValidator,
        protected AuditLogger $auditLogger
    ) {}

    /**
     * Process a full or partial refund for a transaction.
     *
     * @param string      $transactionId The transaction ID (external_id)
     * @param float|null  $amount        The amount to refund (null for full refund)
     * @param string|null $reason        The reason for the refund
     *
     * @throws TransactionNotFoundException
     * @throws InvalidRefundException
     * @throws RefundProcessingException
     */
    public function refund(string $transactionId, ?float $amount = null, ?string $reason = null): RefundPaymentResponse
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
            ->withRequestId();

        if ($amount !== null) {
            $logContext->withAmount($amount);
        }

        Log::channel('payment')->info('Starting refund processing', $logContext->toArray());

        // Validate refund eligibility (payment method, time window, settlement, etc.)
        $this->eligibilityValidator->validateEligibility($transaction);

        // Validate transaction status
        if (! in_array($transaction->status, ['approved', 'partially_refunded'])) {
            throw new InvalidRefundException(
                'Transaction cannot be refunded in current status: '.$transaction->status
            );
        }

        // Calculate refund amount
        $refundAmount = $amount ?? $transaction->amount;
        $totalRefunded = $this->refundRepo->getTotalRefunded($transaction->id);

        // Validate refund amount
        if (($totalRefunded + $refundAmount) > $transaction->amount) {
            throw new InvalidRefundException(
                sprintf('Refund amount exceeds transaction amount. Transaction: %s, Already refunded: %s, Requested: %s', $transaction->amount, $totalRefunded, $refundAmount)
            );
        }

        if ($refundAmount <= 0) {
            throw new InvalidRefundException('Refund amount must be greater than zero');
        }

        // Validate external_id exists
        if (empty($transaction->external_id)) {
            throw new InvalidRefundException('Transaction does not have an external_id. Cannot process refund.');
        }

        DB::beginTransaction();

        try {
            // Process refund with gateway
            $gateway = $this->gatewayManager->gateway($transaction->gateway);
            $response = $gateway->refundPayment($transaction->external_id, $refundAmount);

            // Record refund
            $refund = $this->refundRepo->create([
                'transaction_id' => $transaction->id,
                'amount' => $refundAmount,
                'reason' => $reason,
                'status' => 'completed',
                'gateway_refund_id' => $response->refundId ?? null,
                'gateway_response' => $response->details,
                'processed_at' => now(),
            ]);

            // Update transaction status
            $newTotalRefunded = $totalRefunded + $refundAmount;
            $newStatus = $newTotalRefunded >= $transaction->amount
                ? 'refunded'
                : 'partially_refunded';

            $this->transactionRepo->update($transaction->id, ['status' => $newStatus]);

            DB::commit();

            $transaction->refresh();

            // Log audit trail
            $correlationId = is_string($logContext->toArray()['correlation_id'] ?? null)
                ? $logContext->toArray()['correlation_id']
                : null;

            $this->auditLogger->logRefund($transaction, $refund, $correlationId, [
                'previous_status' => $transaction->getOriginal('status'),
                'total_refunded' => $newTotalRefunded,
            ]);

            $logContext->withTransaction($transaction)
                ->withDuration($startTime)
                ->with('refund_id', $refund->id)
                ->with('refund_amount', $refundAmount)
                ->with('total_refunded', $newTotalRefunded);

            Log::channel('payment')->info('Refund processed successfully', $logContext->toArray());

            // Dispatch refund event
            PaymentRefunded::dispatch($transaction, $refund);

            return $response;
        } catch (\Throwable $throwable) {
            DB::rollBack();

            $logContext->withError($throwable)
                ->withDuration($startTime);

            Log::channel('payment')->error('Refund processing failed', $logContext->toArray());

            if ($throwable instanceof InvalidRefundException || $throwable instanceof TransactionNotFoundException) {
                throw $throwable;
            }

            throw new RefundProcessingException($throwable->getMessage(), $throwable->getCode(), $throwable);
        }
    }

    /**
     * Get total refunded amount for a transaction.
     */
    public function getTotalRefunded(int $transactionId): float
    {
        return $this->refundRepo->getTotalRefunded($transactionId);
    }

    /**
     * Get refund history for a transaction.
     *
     * @return LengthAwarePaginator<int, Refund>
     */
    public function getRefundHistory(int $transactionId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->refundRepo->getRefundHistory($transactionId, $perPage);
    }
}
