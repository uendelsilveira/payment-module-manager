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
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
        $context = LogContext::create()
            ->withCorrelationId()
            ->withGateway($this->gateway)
            ->with('webhook_data', $this->webhookData)
            ->with('transaction_id', $this->transactionId)
            ->with('attempt', $this->attempts())
            ->maskSensitiveData();

        Log::channel('webhook')->info('Processing webhook asynchronously', $context->toArray());

        try {
            // Process based on gateway
            if ($this->gateway === 'mercadopago') {
                $this->processMercadoPagoWebhook($paymentService, $context);
            }

            Log::channel('webhook')->info('Webhook processed successfully', $context->toArray());
        } catch (Throwable $e) {
            $context->withError($e);
            Log::channel('webhook')->error('Webhook processing failed', $context->toArray());

            // Re-throw to trigger retry
            throw $e;
        }
    }

    /**
     * Process MercadoPago webhook
     */
    private function processMercadoPagoWebhook(PaymentService $paymentService, LogContext $context): void
    {
        $notificationType = $this->webhookData['type'] ?? null;

        if ($notificationType !== 'payment') {
            Log::channel('webhook')->warning('Unsupported notification type', $context->toArray());

            return;
        }

        $paymentId = $this->webhookData['data']['id'] ?? null;

        if (! $paymentId) {
            Log::channel('webhook')->error('Payment ID missing from webhook', $context->toArray());

            return;
        }

        // Find transaction by external_id and update status
        $transaction = \UendelSilveira\PaymentModuleManager\Models\Transaction::where('external_id', $paymentId)->first();

        if (! $transaction) {
            Log::channel('webhook')->warning('Transaction not found for payment ID', $context->with('payment_id', $paymentId)->toArray());

            return;
        }

        // Refresh transaction status from gateway
        $paymentService->getPaymentDetails($transaction);
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        $context = LogContext::create()
            ->withCorrelationId()
            ->withGateway($this->gateway)
            ->withError($exception)
            ->with('transaction_id', $this->transactionId)
            ->with('attempts', $this->attempts());

        Log::channel('webhook')->critical('Webhook processing failed permanently after retries', $context->toArray());
    }
}
