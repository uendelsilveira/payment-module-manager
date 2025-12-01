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
use UendelSilveira\PaymentModuleManager\Contracts\TransactionRepositoryInterface;
use UendelSilveira\PaymentModuleManager\Events\PaymentStatusChanged;
use UendelSilveira\PaymentModuleManager\Facades\PaymentGateway;
use UendelSilveira\PaymentModuleManager\Models\Transaction;

class ProcessWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public string $gateway,
        public array $payload
    ) {}

    public function handle(TransactionRepositoryInterface $transactionRepository): void
    {
        try {
            /** @var array{transaction_id: string, status: string, payment_method: string, amount: float, metadata: array<string, mixed>} $processedData */
            $processedData = PaymentGateway::gateway($this->gateway)->handleWebhook($this->payload);

            $externalId = $processedData['transaction_id'];

            $transaction = $transactionRepository->findBy('external_id', $externalId);

            if (! $transaction instanceof Transaction) {
                Log::channel('payment')->error('Transaction not found for webhook', [
                    'gateway' => $this->gateway,
                    'external_id' => $externalId,
                ]);

                return;
            }

            if (in_array($transaction->status, ['completed', 'failed', 'refunded', 'cancelled'])) {
                Log::channel('payment')->info('Transaction already in final state, skipping webhook', [
                    'transaction_id' => $transaction->id,
                    'current_status' => $transaction->status,
                    'new_status' => $processedData['status'],
                ]);

                return;
            }

            $oldStatus = $transaction->status;
            $newStatus = $processedData['status'];

            if ($oldStatus !== $newStatus) {
                $transactionRepository->update($transaction->id, [
                    'status' => $newStatus,
                    'metadata' => array_merge($transaction->metadata ?? [], ['webhook_processed_at' => now()->toIso8601String()]),
                ]);

                Log::channel('payment')->info('Webhook processed successfully', [
                    'gateway' => $this->gateway,
                    'transaction_id' => $transaction->id,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                ]);

                $freshTransaction = $transaction->fresh();

                if ($freshTransaction instanceof Transaction) {
                    event(new PaymentStatusChanged($freshTransaction, $oldStatus, $newStatus));
                }
            }
        } catch (\Exception $exception) {
            Log::channel('payment')->error('Error processing webhook', [
                'gateway' => $this->gateway,
                'error' => $exception->getMessage(),
                'payload' => $this->payload,
            ]);

            throw $exception;
        }
    }
}
