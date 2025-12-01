<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 19/11/2025 14:24:12
*/

namespace UendelSilveira\PaymentModuleManager\Services;

use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use UendelSilveira\PaymentModuleManager\DTOs\CancelPaymentResponse;
use UendelSilveira\PaymentModuleManager\DTOs\RefundPaymentResponse;
use UendelSilveira\PaymentModuleManager\Enums\PaymentStatus;
use UendelSilveira\PaymentModuleManager\Events\PaymentFailed;
use UendelSilveira\PaymentModuleManager\Events\PaymentProcessed;
use UendelSilveira\PaymentModuleManager\Events\PaymentStatusChanged;
use UendelSilveira\PaymentModuleManager\Events\RefundProcessed;
use UendelSilveira\PaymentModuleManager\Models\Transaction;
use UendelSilveira\PaymentModuleManager\PaymentGatewayManager;
use UendelSilveira\PaymentModuleManager\Repositories\TransactionRepository;
use UendelSilveira\PaymentModuleManager\Support\LogContext;

class PaymentService
{
    public function __construct(
        protected PaymentGatewayManager $paymentGatewayManager,
        protected TransactionRepository $transactionRepository,
        protected RetryService $retryService
    ) {}

    /**
     * @param array<string, mixed> $data
     *
     * @throws Exception
     */
    public function processPayment(array $data): Transaction
    {
        $startTime = microtime(true);
        $correlationIdArray = LogContext::create()->withCorrelationId()->toArray();
        $correlationId = is_string($correlationIdArray['correlation_id'] ?? null) ? $correlationIdArray['correlation_id'] : null;

        $gatewayName = is_string($data['gateway'] ?? null) ? $data['gateway'] : $this->paymentGatewayManager->getDefaultGateway();
        $amount = is_float($data['amount'] ?? null) || is_int($data['amount'] ?? null) ? (float) $data['amount'] : 0.0;
        $paymentMethod = is_string($data['payment_method_id'] ?? null) ? $data['payment_method_id'] : 'default';

        $logContext = LogContext::create()
            ->withCorrelationId($correlationId)
            ->withGateway($gatewayName)
            ->withAmount($amount)
            ->withPaymentMethod($paymentMethod)
            ->withRequestId()
            ->maskSensitiveData();

        Log::channel('payment')->info('Starting payment processing', $logContext->toArray());

        $paymentGateway = $this->paymentGatewayManager->gateway($gatewayName);

        $this->validateMonetaryLimits($gatewayName, $paymentMethod, $amount);

        $result = DB::transaction(function () use ($data, $paymentGateway, $startTime, $logContext, $gatewayName, $amount): Transaction {
            $description = is_string($data['description'] ?? null) ? $data['description'] : '';

            $transactionData = [
                'gateway' => $gatewayName,
                'amount' => $amount,
                'description' => $description,
                'status' => PaymentStatus::PENDING->value,
            ];

            if (isset($data['_idempotency_key']) && is_string($data['_idempotency_key'])) {
                $transactionData['idempotency_key'] = $data['_idempotency_key'];
            }

            $transaction = $this->transactionRepository->create($transactionData);

            $logContext->withTransactionId($transaction->id);

            Log::channel('transaction')->info('Transaction created', $logContext->toArray());

            try {
                $gatewayResponse = $paymentGateway->processPayment($data);

                $this->transactionRepository->update($transaction->id, [
                    'external_id' => $gatewayResponse->transactionId,
                    'status' => $gatewayResponse->status->value,
                    'metadata' => array_merge($data, $gatewayResponse->details),
                ]);

                $transaction->refresh();

                $logContext->withTransaction($transaction)->withDuration($startTime);

                Log::channel('payment')->info('Payment processed successfully', $logContext->toArray());

                PaymentProcessed::dispatch($transaction);

            } catch (Throwable $throwable) {
                $this->transactionRepository->update($transaction->id, [
                    'status' => PaymentStatus::FAILED->value,
                    'metadata' => $data,
                ]);

                $transaction->refresh();

                $logContext->withTransaction($transaction)
                    ->withError($throwable)
                    ->withDuration($startTime);

                Log::channel('payment')->error('Payment processing failed', $logContext->toArray());

                PaymentFailed::dispatch($data, $throwable);

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

        $paymentGateway = $this->paymentGatewayManager->gateway($transaction->gateway);
        $paymentStatus = $paymentGateway->getPaymentStatus($transaction->external_id);
        $newStatus = $paymentStatus->value;

        if ($newStatus !== $transaction->status) {
            $oldStatus = $transaction->status;

            $logContext->with('old_status', $oldStatus)
                ->with('new_status', $newStatus);

            Log::channel('transaction')->info('Transaction status changed in gateway', $logContext->toArray());

            $this->transactionRepository->update($transaction->id, ['status' => $newStatus]);
            $transaction->refresh();

            PaymentStatusChanged::dispatch($transaction, $oldStatus, $newStatus);
        }

        $logContext->withDuration($startTime);
        Log::channel('payment')->info('Payment details fetched successfully', $logContext->toArray());

        return $transaction;
    }

    /**
     * @return Collection<int, Transaction>
     */
    public function getFailedTransactions(): Collection
    {
        return $this->transactionRepository->getFailedToReprocess();
    }

    public function reprocess(Transaction $transaction): Transaction
    {
        $startTime = microtime(true);
        $maxAttemptsConfig = config('payment.retry.max_attempts', 3);
        $maxAttempts = is_int($maxAttemptsConfig) ? $maxAttemptsConfig : (int) $maxAttemptsConfig;

        $logContext = LogContext::create()
            ->withCorrelationId()
            ->withTransaction($transaction)
            ->withRetry($transaction->retries_count + 1, $maxAttempts)
            ->withRequestId();

        // Check if eligible for retry
        if (! $this->retryService->isEligibleForRetry($transaction, $maxAttempts)) {
            Log::channel('payment')->warning('Transaction not eligible for retry', $logContext->toArray());

            throw new Exception('Transaction is not eligible for retry');
        }

        Log::channel('payment')->info('Reprocessing transaction', $logContext->toArray());

        $paymentGateway = $this->paymentGatewayManager->gateway($transaction->gateway);
        $chargeData = (array) $transaction->metadata;

        try {
            $gatewayResponse = $paymentGateway->processPayment($chargeData);

            $this->transactionRepository->update($transaction->id, [
                'status' => $gatewayResponse->status->value,
                'external_id' => $gatewayResponse->transactionId,
                'metadata' => array_merge($chargeData, $gatewayResponse->details),
                'retries_count' => $transaction->retries_count + 1,
                'last_attempt_at' => now(),
            ]);

            $transaction->refresh();

            $logContext->withTransaction($transaction)
                ->withDuration($startTime)
                ->with('success', true);

            Log::channel('payment')->info('Transaction reprocessed successfully', $logContext->toArray());

        } catch (Throwable $throwable) {
            $this->transactionRepository->update($transaction->id, [
                'retries_count' => $transaction->retries_count + 1,
                'last_attempt_at' => now(),
            ]);

            $transaction->refresh();

            $logContext->withTransaction($transaction)
                ->withError($throwable)
                ->withDuration($startTime)
                ->with('success', false);

            Log::channel('payment')->error('Transaction reprocessing failed', $logContext->toArray());

            throw $throwable;
        }

        return $transaction;
    }

    public function refundPayment(Transaction $transaction, ?float $amount = null): RefundPaymentResponse
    {
        $startTime = microtime(true);

        $logContext = LogContext::create()
            ->withCorrelationId()
            ->withTransaction($transaction)
            ->withRequestId();

        if ($amount !== null) {
            $logContext->withAmount($amount);
        }

        Log::channel('payment')->info('Starting refund processing', $logContext->toArray());

        if (empty($transaction->external_id)) {
            throw new Exception('Transação não possui external_id. Não é possível estornar.');
        }

        if ($transaction->status === PaymentStatus::REFUNDED->value) {
            throw new Exception('Esta transação já foi estornada.');
        }

        if ($transaction->status !== PaymentStatus::APPROVED->value && $transaction->status !== 'authorized') {
            throw new Exception('Apenas pagamentos aprovados ou autorizados podem ser estornados. Status atual: '.$transaction->status);
        }

        try {
            $paymentGateway = $this->paymentGatewayManager->gateway($transaction->gateway);
            $refundResponse = $paymentGateway->refundPayment($transaction->external_id, $amount);

            $this->transactionRepository->update($transaction->id, [
                'status' => PaymentStatus::REFUNDED->value,
                'metadata' => array_merge((array) $transaction->metadata, ['refund' => $refundResponse->details]),
            ]);

            $transaction->refresh();

            $logContext->withTransaction($transaction)
                ->withDuration($startTime)
                ->with('refund_data', $refundResponse->details);

            Log::channel('payment')->info('Refund processed successfully', $logContext->toArray());

            RefundProcessed::dispatch($transaction, $amount ?? $transaction->amount);

            return $refundResponse;
        } catch (Throwable $throwable) {
            $logContext->withError($throwable)
                ->withDuration($startTime);

            Log::channel('payment')->error('Refund processing failed', $logContext->toArray());

            throw $throwable;
        }
    }

    public function cancelPayment(Transaction $transaction): CancelPaymentResponse
    {
        $startTime = microtime(true);

        $logContext = LogContext::create()
            ->withCorrelationId()
            ->withTransaction($transaction)
            ->withRequestId();

        Log::channel('payment')->info('Starting payment cancellation', $logContext->toArray());

        if (empty($transaction->external_id)) {
            throw new Exception('Transação não possui external_id. Não é possível cancelar.');
        }

        if ($transaction->status === PaymentStatus::CANCELLED->value) {
            throw new Exception('Esta transação já foi cancelada.');
        }

        if ($transaction->status !== PaymentStatus::PENDING->value && $transaction->status !== 'in_process') {
            throw new Exception('Apenas pagamentos pendentes ou em processamento podem ser cancelados. Status atual: '.$transaction->status);
        }

        try {
            $paymentGateway = $this->paymentGatewayManager->gateway($transaction->gateway);
            $cancelResponse = $paymentGateway->cancelPayment($transaction->external_id);

            $this->transactionRepository->update($transaction->id, [
                'status' => PaymentStatus::CANCELLED->value,
                'metadata' => array_merge((array) $transaction->metadata, ['cancellation' => $cancelResponse->details]),
            ]);

            $transaction->refresh();

            $logContext->withTransaction($transaction)
                ->withDuration($startTime)
                ->with('cancel_data', $cancelResponse->details);

            Log::channel('payment')->info('Payment cancelled successfully', $logContext->toArray());

            return $cancelResponse;
        } catch (Throwable $throwable) {
            $logContext->withError($throwable)
                ->withDuration($startTime);

            Log::channel('payment')->error('Payment cancellation failed', $logContext->toArray());

            throw $throwable;
        }
    }

    protected function validateMonetaryLimits(string $gatewayName, string $paymentMethod, float $amount): void
    {
        $config = config('payment.monetary_limits');
        $config = is_array($config) ? $config : [];

        $gatewayConfig = is_array($config[$gatewayName] ?? null) ? $config[$gatewayName] : [];
        $methodConfig = is_array($gatewayConfig[$paymentMethod] ?? null) ? $gatewayConfig[$paymentMethod] : [];
        $min = is_int($methodConfig['min'] ?? null) || is_float($methodConfig['min'] ?? null) ? (int) $methodConfig['min'] : null;
        $max = is_int($methodConfig['max'] ?? null) || is_float($methodConfig['max'] ?? null) ? (int) $methodConfig['max'] : null;

        if (is_null($min) || is_null($max)) {
            $defaultConfig = is_array($gatewayConfig['default'] ?? null) ? $gatewayConfig['default'] : [];
            $min = is_int($defaultConfig['min'] ?? null) || is_float($defaultConfig['min'] ?? null) ? (int) $defaultConfig['min'] : $min;
            $max = is_int($defaultConfig['max'] ?? null) || is_float($defaultConfig['max'] ?? null) ? (int) $defaultConfig['max'] : $max;
        }

        if (is_null($min) || is_null($max)) {
            $globalConfig = is_array($config['global'] ?? null) ? $config['global'] : [];
            $min = is_int($globalConfig['min'] ?? null) || is_float($globalConfig['min'] ?? null) ? (int) $globalConfig['min'] : 0;
            $max = is_int($globalConfig['max'] ?? null) || is_float($globalConfig['max'] ?? null) ? (int) $globalConfig['max'] : PHP_INT_MAX;
        }

        $amountInSmallestUnit = (int) round($amount * 100);

        if ($amountInSmallestUnit < $min) {
            $minFormatted = is_int($min) ? ($min / 100) : $min;

            throw new Exception(sprintf("O valor do pagamento (%s) está abaixo do limite mínimo permitido (%s) para o gateway '%s' e método '%s'.", $amount, $minFormatted, $gatewayName, $paymentMethod));
        }

        if ($amountInSmallestUnit > $max) {
            $maxFormatted = is_int($max) ? ($max / 100) : $max;

            throw new Exception(sprintf("O valor do pagamento (%s) excede o limite máximo permitido (%s) para o gateway '%s' e método '%s'.", $amount, $maxFormatted, $gatewayName, $paymentMethod));
        }
    }
}
