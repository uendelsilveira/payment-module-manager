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
        public readonly ?int $transactionId = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(PaymentService $paymentService): void
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
            // Process based on gateway
            if ($this->gateway === 'mercadopago') {
                $this->processMercadoPagoWebhook($paymentService, $logContext);
            }

            Log::channel('webhook')->info('Webhook processed successfully', $logContext->toArray());
        } catch (Throwable $throwable) {
            $logContext->withError($throwable);
            Log::channel('webhook')->error('Webhook processing failed', $logContext->toArray());

            // Re-throw to trigger retry
            throw $throwable;
        }
    }

    /**
     * Process MercadoPago webhook
     */
    private function processMercadoPagoWebhook(PaymentService $paymentService, LogContext $logContext): void
    {
        $notificationType = $this->webhookData['type'] ?? null;

        if ($notificationType !== 'payment') {
            Log::channel('webhook')->warning('Unsupported notification type', $logContext->toArray());

            return;
        }

        $paymentId = $this->webhookData['data']['id'] ?? null;

        if (! $paymentId) {
            Log::channel('webhook')->error('Payment ID missing from webhook', $logContext->toArray());

            return;
        }

        // Find transaction by external_id and update status
        /** @var \UendelSilveira\PaymentModuleManager\Models\Transaction|null $transaction */
        $transaction = \UendelSilveira\PaymentModuleManager\Models\Transaction::where('external_id', $paymentId)->first();

        if (! $transaction) {
            Log::channel('webhook')->warning('Transaction not found for payment ID', $logContext->with('payment_id', $paymentId)->toArray());

            return;
        }

        // Refresh transaction status from gateway
        $paymentService->getPaymentDetails($transaction);
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
