<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 04/11/2025 16:09:38
*/

namespace UendelSilveira\PaymentModuleManager\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use UendelSilveira\PaymentModuleManager\Contracts\TransactionRepositoryInterface;
use UendelSilveira\PaymentModuleManager\Events\PaymentFailed;
use UendelSilveira\PaymentModuleManager\Events\PaymentProcessed;
use UendelSilveira\PaymentModuleManager\Events\PaymentStatusChanged;
use UendelSilveira\PaymentModuleManager\Models\Transaction;
use UendelSilveira\PaymentModuleManager\Support\LogContext;

class PaymentService
{
    public function __construct(protected GatewayManager $gatewayManager, protected TransactionRepositoryInterface $transactionRepository) {}

    /**
     * @param array<string, mixed> $data
     */
    public function processPayment(array $data): Transaction
    {
        $startTime = microtime(true);
        $correlationIdArray = LogContext::create()->withCorrelationId()->toArray();
        $correlationId = is_string($correlationIdArray['correlation_id'] ?? null) ? $correlationIdArray['correlation_id'] : null;

        $gateway = is_string($data['method'] ?? null) ? $data['method'] : '';
        $amount = is_float($data['amount'] ?? null) || is_int($data['amount'] ?? null) ? (float) $data['amount'] : 0.0;
        $paymentMethod = is_string($data['payment_method_id'] ?? null) ? $data['payment_method_id'] : 'unknown';

        $logContext = LogContext::create()
            ->withCorrelationId($correlationId)
            ->withGateway($gateway)
            ->withAmount($amount)
            ->withPaymentMethod($paymentMethod)
            ->withRequestId()
            ->maskSensitiveData();

        Log::channel('payment')->info('Starting payment processing', $logContext->toArray());

        $paymentGateway = $this->gatewayManager->create($gateway);

        $result = DB::transaction(function () use ($data, $paymentGateway, $startTime, $logContext): Transaction {
            $gateway = is_string($data['method'] ?? null) ? $data['method'] : '';
            $amount = is_float($data['amount'] ?? null) || is_int($data['amount'] ?? null) ? (float) $data['amount'] : 0.0;
            $description = is_string($data['description'] ?? null) ? $data['description'] : '';

            $transactionData = [
                'gateway' => $gateway,
                'amount' => $amount,
                'description' => $description,
                'status' => 'pending',
            ];

            // Add idempotency key if provided
            if (isset($data['_idempotency_key']) && is_string($data['_idempotency_key'])) {
                $transactionData['idempotency_key'] = $data['_idempotency_key'];
            }

            $transaction = $this->transactionRepository->create($transactionData);

            $logContext->withTransactionId($transaction->id);

            Log::channel('transaction')->info('Transaction created', $logContext->toArray());

            try {
                $gatewayResponse = $paymentGateway->charge($amount, $data);

                $externalId = is_string($gatewayResponse['id'] ?? null) ? $gatewayResponse['id'] : null;
                $status = is_string($gatewayResponse['status'] ?? null) ? $gatewayResponse['status'] : 'unknown';

                $transaction->external_id = $externalId;
                $transaction->status = $status;
                $transaction->metadata = array_merge($data, $gatewayResponse);
                $transaction->save();

                $logContext->withTransaction($transaction)->withDuration($startTime);

                Log::channel('payment')->info('Payment processed successfully', $logContext->toArray());

                // Dispatch event
                PaymentProcessed::dispatch($transaction, $gatewayResponse);

            } catch (Throwable $throwable) {
                $transaction->status = 'failed';
                $transaction->metadata = $data;
                $transaction->save();

                $logContext->withTransaction($transaction)
                    ->withError($throwable)
                    ->withDuration($startTime);

                Log::channel('payment')->error('Payment processing failed', $logContext->toArray());

                // Dispatch event
                PaymentFailed::dispatch($transaction, $throwable, $data);

                throw $throwable;
            }

            return $transaction;
        });

        assert($result instanceof Transaction);

        return $result;
    }

    public function getPaymentDetails(Transaction $transaction): Transaction
    {
        $startTime = microtime(true);

        $logContext = LogContext::create()
            ->withCorrelationId()
            ->withTransaction($transaction)
            ->withRequestId();

        Log::channel('payment')->info('Fetching payment details', $logContext->toArray());

        if (empty($transaction->external_id)) {
            Log::channel('payment')->warning('Transaction has no external_id for query', $logContext->toArray());

            return $transaction;
        }

        $paymentGateway = $this->gatewayManager->create($transaction->gateway);
        $gatewayResponse = $paymentGateway->getPayment($transaction->external_id);

        $responseStatus = is_string($gatewayResponse['status'] ?? null) ? $gatewayResponse['status'] : 'unknown';

        if ($responseStatus !== $transaction->status) {
            $oldStatus = $transaction->status;
            $newStatus = $responseStatus;

            $logContext->with('old_status', $oldStatus)
                ->with('new_status', $newStatus);

            Log::channel('transaction')->info('Transaction status changed in gateway', $logContext->toArray());

            $transaction->status = $newStatus;
            $transaction->metadata = array_merge((array) $transaction->metadata, $gatewayResponse);
            $transaction->save();

            // Dispatch event
            PaymentStatusChanged::dispatch($transaction, $oldStatus, $newStatus);
        }

        $logContext->withDuration($startTime);
        Log::channel('payment')->info('Payment details fetched successfully', $logContext->toArray());

        return $transaction;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Transaction>
     */
    public function getFailedTransactions()
    {
        return Transaction::where('status', 'failed')
            ->where(function ($query): void {
                $query->whereNull('last_attempt_at')
                    ->orWhere('last_attempt_at', '<', Carbon::now()->subMinutes(5));
            })
            ->where('retries_count', '<', 3)
            ->get();
    }

    public function reprocess(Transaction $transaction): Transaction
    {
        $startTime = microtime(true);
        $maxAttemptsConfig = config('payment.retry.max_attempts', 3);
        $maxAttempts = is_int($maxAttemptsConfig) ? $maxAttemptsConfig : 3;

        $logContext = LogContext::create()
            ->withCorrelationId()
            ->withTransaction($transaction)
            ->withRetry($transaction->retries_count + 1, $maxAttempts)
            ->withRequestId();

        Log::channel('payment')->info('Reprocessing transaction', $logContext->toArray());

        $paymentGateway = $this->gatewayManager->create($transaction->gateway);
        $chargeData = (array) $transaction->metadata;

        try {
            $gatewayResponse = $paymentGateway->charge($transaction->amount, $chargeData);

            $status = is_string($gatewayResponse['status'] ?? null) ? $gatewayResponse['status'] : 'unknown';
            $externalId = is_string($gatewayResponse['id'] ?? null) ? $gatewayResponse['id'] : null;

            $transaction->status = $status;
            $transaction->external_id = $externalId;
            $transaction->metadata = array_merge($chargeData, $gatewayResponse);
            $transaction->retries_count++;
            $transaction->last_attempt_at = now();
            $transaction->save();

            $logContext->withTransaction($transaction)
                ->withDuration($startTime)
                ->with('success', true);

            Log::channel('payment')->info('Transaction reprocessed successfully', $logContext->toArray());

        } catch (Throwable $throwable) {
            $transaction->retries_count++;
            $transaction->last_attempt_at = now();
            $transaction->save();

            $logContext->withTransaction($transaction)
                ->withError($throwable)
                ->withDuration($startTime)
                ->with('success', false);

            Log::channel('payment')->error('Transaction reprocessing failed', $logContext->toArray());

            throw $throwable;
        }

        return $transaction;
    }
}
