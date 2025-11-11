<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 04/11/2025 16:09:38
*/

namespace UendelSilveira\PaymentModuleManager\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;
use UendelSilveira\PaymentModuleManager\Models\Transaction; // Importar o modelo Transaction
use UendelSilveira\PaymentModuleManager\PaymentGatewayManager; // Importar o PaymentGatewayManager
use UendelSilveira\PaymentModuleManager\Services\PaymentService;
use UendelSilveira\PaymentModuleManager\Support\LogContext;

/**
 * Job to process payment webhooks asynchronously
 */
class ProcessWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * Delete the job if its models no longer exist.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     *
     * @param array<string, mixed> $webhookData
     */
    public function __construct(
        public readonly string $gateway,
        public readonly array $webhookData,
        public readonly ?int $transactionId = null // transactionId might be redundant, but keeping for now
    ) {}

    /**
     * Execute the job.
     */
    public function handle(PaymentGatewayManager $paymentGatewayManager, PaymentService $paymentService): void
    {
        $logContext = LogContext::create()
            ->withCorrelationId()
            ->withGateway($this->gateway)
            ->with('webhook_data', $this->webhookData)
            ->with('transaction_id', $this->transactionId)
            ->with('attempt', $this->attempts())
            ->maskSensitiveData();

        Log::channel('webhook')->info('Processing webhook asynchronously', $logContext->toArray());

        try {
            // Get the specific gateway instance
            $gatewayInstance = $paymentGatewayManager->gateway($this->gateway);

            // Process the webhook data using the gateway's method
            $processedInfo = $gatewayInstance->processWebhook($this->webhookData);

            $externalPaymentId = $processedInfo['payment_id'] ?? null;
            // $newStatusEnum = $processedInfo['status'] ?? null; // Not directly used to update, getPaymentDetails will fetch

            if (! $externalPaymentId) {
                Log::channel('webhook')->error('Processed webhook info missing payment_id', $logContext->toArray());

                return; // Or throw an exception
            }

            // Find the local transaction
            /** @var Transaction|null $transaction */
            $transaction = Transaction::where('external_id', $externalPaymentId)->first();

            if (! $transaction) {
                Log::channel('webhook')->warning('Transaction not found for external ID', $logContext->with('external_id', $externalPaymentId)->toArray());

                return;
            }

            // Use PaymentService to get and update payment details, which will also dispatch events
            // This ensures consistency and leverages the existing logic for status updates.
            $paymentService->getPaymentDetails($transaction);

            Log::channel('webhook')->info('Webhook processed successfully', $logContext->toArray());
        } catch (Throwable $throwable) {
            $logContext->withError($throwable);
            Log::channel('webhook')->error('Webhook processing failed', $logContext->toArray());

            // Re-throw to trigger retry
            throw $throwable;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $throwable): void
    {
        $logContext = LogContext::create()
            ->withCorrelationId()
            ->withGateway($this->gateway)
            ->withError($throwable)
            ->with('transaction_id', $this->transactionId)
            ->with('attempts', $this->attempts());

        Log::channel('webhook')->critical('Webhook processing failed permanently after retries', $logContext->toArray());
    }
}
