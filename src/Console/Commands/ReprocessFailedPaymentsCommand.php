<?php

namespace UendelSilveira\PaymentModuleManager\Console\Commands;

use Illuminate\Console\Command;
use UendelSilveira\PaymentModuleManager\Enums\PaymentStatus;
use UendelSilveira\PaymentModuleManager\Models\Transaction;
use UendelSilveira\PaymentModuleManager\Services\PaymentService;

class ReprocessFailedPaymentsCommand extends Command
{
    protected $signature = 'payments:reprocess-failed';

    protected $description = 'Reprocess failed payment transactions.';

    public function __construct(protected PaymentService $paymentService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Starting to reprocess failed payments...');

        $failedTransactions = Transaction::where('status', PaymentStatus::FAILED)->get();

        if ($failedTransactions->isEmpty()) {
            $this->info('No failed transactions to reprocess.');

            return 0;
        }

        $this->info(sprintf('Found %d failed transactions to reprocess.', $failedTransactions->count()));

        foreach ($failedTransactions as $failedTransaction) {
            $this->info('Reprocessing transaction ID: '.$failedTransaction->id);

            try {
                $payload = is_array($failedTransaction->metadata) ? $failedTransaction->metadata : [];
                $this->paymentService->processPayment($payload);
                $this->info(sprintf('Transaction ID: %d reprocessed successfully.', $failedTransaction->id));
            } catch (\Exception $e) {
                $this->error(sprintf('Failed to reprocess transaction ID: %d. Error: %s', $failedTransaction->id, $e->getMessage()));
            }
        }

        $this->info('Finished reprocessing failed payments.');

        return 0;
    }
}
