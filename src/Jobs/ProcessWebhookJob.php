<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 19/11/2025 14:24:12
*/

namespace UendelSilveira\PaymentModuleManager\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use UendelSilveira\PaymentModuleManager\Facades\PaymentGateway;

class ProcessWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public string $gateway,
        public array $payload
    ) {}

    public function handle(): void
    {
        try {
            /** @phpstan-ignore-next-line */
            $processedData = PaymentGateway::gateway($this->gateway)->processWebhook($this->payload);

            // TODO: Implement logic to update the application's database with the processed data.
            // For example, find the transaction by `transaction_id` and update its status.

            Log::channel('payment')->info('Webhook processed successfully', [
                'gateway' => $this->gateway,
                'data' => $processedData,
            ]);

        } catch (\Exception $exception) {
            Log::channel('payment')->error('Error processing webhook', [
                'gateway' => $this->gateway,
                'error' => $exception->getMessage(),
                'payload' => $this->payload,
            ]);

            // Optionally, re-throw the exception to let the queue handle retries.
            throw $exception;
        }
    }
}
