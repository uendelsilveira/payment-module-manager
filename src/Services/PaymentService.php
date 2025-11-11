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
use UendelSilveira\PaymentModuleManager\Enums\PaymentStatus;
use UendelSilveira\PaymentModuleManager\Events\PaymentFailed;
use UendelSilveira\PaymentModuleManager\Events\PaymentProcessed;
use UendelSilveira\PaymentModuleManager\Events\PaymentStatusChanged;
use UendelSilveira\PaymentModuleManager\Models\Transaction; // Importar o novo gerenciador
use UendelSilveira\PaymentModuleManager\PaymentGatewayManager;
use UendelSilveira\PaymentModuleManager\Support\LogContext; // Importar o PaymentStatus enum

class PaymentService
{
    public function __construct(protected PaymentGatewayManager $paymentGatewayManager, protected TransactionRepositoryInterface $transactionRepository) {}

    /**
     * @param array<string, mixed> $data
     */
    public function processPayment(array $data): Transaction
    {
        $startTime = microtime(true);
        $correlationIdArray = LogContext::create()->withCorrelationId()->toArray();
        $correlationId = is_string($correlationIdArray['correlation_id'] ?? null) ? $correlationIdArray['correlation_id'] : null;

        $gatewayName = is_string($data['gateway'] ?? null) ? $data['gateway'] : $this->paymentGatewayManager->getDefaultGateway();
        $amount = is_float($data['amount'] ?? null) || is_int($data['amount'] ?? null) ? (float) $data['amount'] : 0.0;
        $paymentMethod = is_string($data['payment_method_id'] ?? null) ? $data['payment_method_id'] : 'default'; // Usar 'default' se não especificado

        $logContext = LogContext::create()
            ->withCorrelationId($correlationId)
            ->withGateway($gatewayName)
            ->withAmount($amount)
            ->withPaymentMethod($paymentMethod)
            ->withRequestId()
            ->maskSensitiveData();

        Log::channel('payment')->info('Starting payment processing', $logContext->toArray());

        $paymentGateway = $this->paymentGatewayManager->gateway($gatewayName);

        // Validar limites monetários
        $this->validateMonetaryLimits($gatewayName, $paymentMethod, $amount);

        $result = DB::transaction(function () use ($data, $paymentGateway, $startTime, $logContext, $gatewayName, $amount): Transaction {
            $description = is_string($data['description'] ?? null) ? $data['description'] : '';

            $transactionData = [
                'gateway' => $gatewayName,
                'amount' => $amount,
                'description' => $description,
                'status' => PaymentStatus::PENDING->value, // Usar o enum
            ];

            // Add idempotency key if provided
            if (isset($data['_idempotency_key']) && is_string($data['_idempotency_key'])) {
                $transactionData['idempotency_key'] = $data['_idempotency_key'];
            }

            $transaction = $this->transactionRepository->create($transactionData);

            $logContext->withTransactionId($transaction->id);

            Log::channel('transaction')->info('Transaction created', $logContext->toArray());

            try {
                // Chamar o método processPayment da interface
                $gatewayResponse = $paymentGateway->processPayment($data);

                $externalId = is_string($gatewayResponse['transaction_id'] ?? null) ? $gatewayResponse['transaction_id'] : null;
                $status = $gatewayResponse['status'] instanceof PaymentStatus ? $gatewayResponse['status']->value : (is_string($gatewayResponse['status'] ?? null) ? $gatewayResponse['status'] : PaymentStatus::UNKNOWN->value);

                $transaction->external_id = $externalId;
                $transaction->status = $status;
                $transaction->metadata = array_merge($data, $gatewayResponse);
                $transaction->save();

                $logContext->withTransaction($transaction)->withDuration($startTime);

                Log::channel('payment')->info('Payment processed successfully', $logContext->toArray());

                // Dispatch event
                PaymentProcessed::dispatch($transaction, $gatewayResponse);

            } catch (Throwable $throwable) {
                $transaction->status = PaymentStatus::FAILED->value; // Usar o enum
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

        $paymentGateway = $this->paymentGatewayManager->gateway($transaction->gateway);
        // Chamar o método getPaymentStatus da interface
        $paymentStatus = $paymentGateway->getPaymentStatus($transaction->external_id);
        $newStatus = $paymentStatus->value;

        if ($newStatus !== $transaction->status) {
            $oldStatus = $transaction->status;

            $logContext->with('old_status', $oldStatus)
                ->with('new_status', $newStatus);

            Log::channel('transaction')->info('Transaction status changed in gateway', $logContext->toArray());

            $transaction->status = $newStatus;
            // Não há um 'gatewayResponse' completo aqui, apenas o status.
            // Se necessário, o gateway pode retornar mais detalhes no getPaymentStatus.
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
        return Transaction::where('status', PaymentStatus::FAILED->value) // Usar o enum
            ->where(function ($query): void {
                $query->whereNull('last_attempt_at')
                    ->orWhere('last_attempt_at', '<', Carbon::now()->subMinutes(5));
            })
            ->where('retries_count', '<', config('payment.retry.max_attempts', 3)) // Usar config diretamente
            ->get();
    }

    public function reprocess(Transaction $transaction): Transaction
    {
        $startTime = microtime(true);
        $maxAttempts = config('payment.retry.max_attempts', 3);

        $logContext = LogContext::create()
            ->withCorrelationId()
            ->withTransaction($transaction)
            ->withRetry($transaction->retries_count + 1, $maxAttempts)
            ->withRequestId();

        Log::channel('payment')->info('Reprocessing transaction', $logContext->toArray());

        $paymentGateway = $this->paymentGatewayManager->gateway($transaction->gateway);
        $chargeData = (array) $transaction->metadata;

        try {
            // Chamar o método processPayment da interface para reprocessar
            $gatewayResponse = $paymentGateway->processPayment($chargeData);

            $status = $gatewayResponse['status'] instanceof PaymentStatus ? $gatewayResponse['status']->value : (is_string($gatewayResponse['status'] ?? null) ? $gatewayResponse['status'] : PaymentStatus::UNKNOWN->value);
            $externalId = is_string($gatewayResponse['transaction_id'] ?? null) ? $gatewayResponse['transaction_id'] : null;

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

    /**
     * @return array<string, mixed>
     */
    public function refundPayment(Transaction $transaction, ?float $amount = null): array
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
            throw new \Exception('Transação não possui external_id. Não é possível estornar.');
        }

        if ($transaction->status === PaymentStatus::REFUNDED->value) { // Usar o enum
            throw new \Exception('Esta transação já foi estornada.');
        }

        if ($transaction->status !== PaymentStatus::APPROVED->value && $transaction->status !== 'authorized') { // Usar o enum
            throw new \Exception('Apenas pagamentos aprovados ou autorizados podem ser estornados. Status atual: '.$transaction->status);
        }

        try {
            $paymentGateway = $this->paymentGatewayManager->gateway($transaction->gateway);
            // Chamar o método refundPayment da interface
            $refundResponse = $paymentGateway->refundPayment($transaction->external_id, $amount);

            $transaction->status = PaymentStatus::REFUNDED->value; // Usar o enum
            $transaction->metadata = array_merge((array) $transaction->metadata, ['refund' => $refundResponse]);
            $transaction->save();

            $logContext->withTransaction($transaction)
                ->withDuration($startTime)
                ->with('refund_data', $refundResponse);

            Log::channel('payment')->info('Refund processed successfully', $logContext->toArray());

            return $refundResponse;
        } catch (Throwable $throwable) {
            $logContext->withError($throwable)
                ->withDuration($startTime);

            Log::channel('payment')->error('Refund processing failed', $logContext->toArray());

            throw $throwable;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function cancelPayment(Transaction $transaction): array
    {
        $startTime = microtime(true);

        $logContext = LogContext::create()
            ->withCorrelationId()
            ->withTransaction($transaction)
            ->withRequestId();

        Log::channel('payment')->info('Starting payment cancellation', $logContext->toArray());

        if (empty($transaction->external_id)) {
            throw new \Exception('Transação não possui external_id. Não é possível cancelar.');
        }

        if ($transaction->status === PaymentStatus::CANCELLED->value) { // Usar o enum
            throw new \Exception('Esta transação já foi cancelada.');
        }

        if ($transaction->status !== PaymentStatus::PENDING->value && $transaction->status !== 'in_process') { // Usar o enum
            throw new \Exception('Apenas pagamentos pendentes ou em processamento podem ser cancelados. Status atual: '.$transaction->status);
        }

        try {
            $paymentGateway = $this->paymentGatewayManager->gateway($transaction->gateway);
            // Chamar o método cancelPayment da interface
            $cancelResponse = $paymentGateway->cancelPayment($transaction->external_id);

            $transaction->status = PaymentStatus::CANCELLED->value; // Usar o enum
            $transaction->metadata = array_merge((array) $transaction->metadata, ['cancellation' => $cancelResponse]);
            $transaction->save();

            $logContext->withTransaction($transaction)
                ->withDuration($startTime)
                ->with('cancel_data', $cancelResponse);

            Log::channel('payment')->info('Payment cancelled successfully', $logContext->toArray());

            return $cancelResponse;
        } catch (Throwable $throwable) {
            $logContext->withError($throwable)
                ->withDuration($startTime);

            Log::channel('payment')->error('Payment cancellation failed', $logContext->toArray());

            throw $throwable;
        }
    }

    /**
     * Validates the transaction amount against configured monetary limits for the given gateway and payment method.
     *
     * @throws \Exception If the amount is outside the allowed limits.
     */
    protected function validateMonetaryLimits(string $gatewayName, string $paymentMethod, float $amount): void
    {
        $config = config('payment.monetary_limits');

        // Try to get limits for the specific gateway and payment method
        $min = $config[$gatewayName][$paymentMethod]['min'] ?? null;
        $max = $config[$gatewayName][$paymentMethod]['max'] ?? null;

        // Fallback to gateway default limits
        if (is_null($min) || is_null($max)) {
            $min = $config[$gatewayName]['default']['min'] ?? $min;
            $max = $config[$gatewayName]['default']['max'] ?? $max;
        }

        // Fallback to global limits
        if (is_null($min) || is_null($max)) {
            $min = $config['global']['min'] ?? 0; // Default min to 0 if not set globally
            $max = $config['global']['max'] ?? PHP_FLOAT_MAX; // Default max to a very large number if not set globally
        }

        // Convert amount to the smallest currency unit for comparison (e.g., cents)
        // Assuming all limits in config are already in the smallest unit.
        $amountInSmallestUnit = (int) round($amount * 100); // Example for BRL/USD

        if ($amountInSmallestUnit < $min) {
            throw new \Exception(sprintf("O valor do pagamento (%s) está abaixo do limite mínimo permitido ({ %s / 100 }) para o gateway '%s' e método '%s'.", $amount, $min, $gatewayName, $paymentMethod));
        }

        if ($amountInSmallestUnit > $max) {
            throw new \Exception(sprintf("O valor do pagamento (%s) excede o limite máximo permitido ({ %s / 100 }) para o gateway '%s' e método '%s'.", $amount, $max, $gatewayName, $paymentMethod));
        }
    }
}
