<?php

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
    protected GatewayManager $gatewayManager;

    protected TransactionRepositoryInterface $transactionRepository;

    public function __construct(GatewayManager $gatewayManager, TransactionRepositoryInterface $transactionRepository)
    {
        $this->gatewayManager = $gatewayManager;
        $this->transactionRepository = $transactionRepository;
    }

    public function processPayment(array $data): Transaction
    {
        $startTime = microtime(true);
        $correlationId = LogContext::create()->withCorrelationId()->toArray()['correlation_id'];

        $context = LogContext::create()
            ->withCorrelationId($correlationId)
            ->withGateway($data['method'])
            ->withAmount($data['amount'])
            ->withPaymentMethod($data['payment_method_id'] ?? 'unknown')
            ->withRequestId()
            ->maskSensitiveData();

        Log::channel('payment')->info('Starting payment processing', $context->toArray());

        $gatewayStrategy = $this->gatewayManager->create($data['method']);

        return DB::transaction(function () use ($data, $gatewayStrategy, $startTime, $context) {
            $transactionData = [
                'gateway' => $data['method'],
                'amount' => $data['amount'],
                'description' => $data['description'],
                'status' => 'pending',
            ];

            // Add idempotency key if provided
            if (isset($data['_idempotency_key'])) {
                $transactionData['idempotency_key'] = $data['_idempotency_key'];
            }

            $transaction = $this->transactionRepository->create($transactionData);

            $context->withTransactionId($transaction->id);

            Log::channel('transaction')->info('Transaction created', $context->toArray());

            try {
                $gatewayResponse = $gatewayStrategy->charge($data['amount'], $data);

                $transaction->external_id = $gatewayResponse['id'];
                $transaction->status = $gatewayResponse['status'];
                $transaction->metadata = array_merge($data, $gatewayResponse);
                $transaction->save();

                $context->withTransaction($transaction)->withDuration($startTime);

                Log::channel('payment')->info('Payment processed successfully', $context->toArray());

                // Dispatch event
                PaymentProcessed::dispatch($transaction, $gatewayResponse);

            } catch (Throwable $e) {
                $transaction->status = 'failed';
                $transaction->metadata = $data;
                $transaction->save();

                $context->withTransaction($transaction)
                    ->withError($e)
                    ->withDuration($startTime);

                Log::channel('payment')->error('Payment processing failed', $context->toArray());

                // Dispatch event
                PaymentFailed::dispatch($transaction, $e, $data);

                throw $e;
            }

            return $transaction;
        });
    }

    public function getPaymentDetails(Transaction $transaction): Transaction
    {
        $startTime = microtime(true);

        $context = LogContext::create()
            ->withCorrelationId()
            ->withTransaction($transaction)
            ->withRequestId();

        Log::channel('payment')->info('Fetching payment details', $context->toArray());

        if (empty($transaction->external_id)) {
            Log::channel('payment')->warning('Transaction has no external_id for query', $context->toArray());

            return $transaction;
        }

        $gatewayStrategy = $this->gatewayManager->create($transaction->gateway);
        $gatewayResponse = $gatewayStrategy->getPayment($transaction->external_id);

        if ($gatewayResponse['status'] !== $transaction->status) {
            $oldStatus = $transaction->status;
            $newStatus = $gatewayResponse['status'];

            $context->with('old_status', $oldStatus)
                ->with('new_status', $newStatus);

            Log::channel('transaction')->info('Transaction status changed in gateway', $context->toArray());

            $transaction->status = $newStatus;
            $transaction->metadata = array_merge((array) $transaction->metadata, $gatewayResponse);
            $transaction->save();

            // Dispatch event
            PaymentStatusChanged::dispatch($transaction, $oldStatus, $newStatus);
        }

        $context->withDuration($startTime);
        Log::channel('payment')->info('Payment details fetched successfully', $context->toArray());

        return $transaction;
    }

    public function getFailedTransactions()
    {
        return Transaction::where('status', 'failed')
            ->where(function ($query) {
                $query->whereNull('last_attempt_at')
                    ->orWhere('last_attempt_at', '<', Carbon::now()->subMinutes(5));
            })
            ->where('retries_count', '<', 3)
            ->get();
    }

    public function reprocess(Transaction $transaction): Transaction
    {
        $startTime = microtime(true);
        $maxAttempts = config('payment.retry.max_attempts', 3);

        $context = LogContext::create()
            ->withCorrelationId()
            ->withTransaction($transaction)
            ->withRetry($transaction->retries_count + 1, $maxAttempts)
            ->withRequestId();

        Log::channel('payment')->info('Reprocessing transaction', $context->toArray());

        $gatewayStrategy = $this->gatewayManager->create($transaction->gateway);
        $chargeData = (array) $transaction->metadata;

        try {
            $gatewayResponse = $gatewayStrategy->charge($transaction->amount, $chargeData);

            $transaction->status = $gatewayResponse['status'];
            $transaction->external_id = $gatewayResponse['id'];
            $transaction->metadata = array_merge($chargeData, $gatewayResponse);
            $transaction->retries_count++;
            $transaction->last_attempt_at = now();
            $transaction->save();

            $context->withTransaction($transaction)
                ->withDuration($startTime)
                ->with('success', true);

            Log::channel('payment')->info('Transaction reprocessed successfully', $context->toArray());

        } catch (Throwable $e) {
            $transaction->retries_count++;
            $transaction->last_attempt_at = now();
            $transaction->save();

            $context->withTransaction($transaction)
                ->withError($e)
                ->withDuration($startTime)
                ->with('success', false);

            Log::channel('payment')->error('Transaction reprocessing failed', $context->toArray());

            throw $e;
        }

        return $transaction;
    }
}
