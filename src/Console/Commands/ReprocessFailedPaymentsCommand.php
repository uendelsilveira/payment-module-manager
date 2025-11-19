<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 19/11/2025 14:24:12
*/

namespace UendelSilveira\PaymentModuleManager\Console\Commands;

use Illuminate\Console\Command;
use UendelSilveira\PaymentModuleManager\Contracts\TransactionRepositoryInterface;
use UendelSilveira\PaymentModuleManager\Services\PaymentService;

class ReprocessFailedPaymentsCommand extends Command
{
    protected $signature = 'payment:reprocess-failed {--limit=100} {--dry-run}';

    protected $description = 'Reprocess failed payment transactions.';

    public function __construct(
        protected PaymentService $paymentService,
        protected TransactionRepositoryInterface $transactions
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Starting to reprocess failed payments...');

        $limitOption = $this->option('limit');
        $limit = is_numeric($limitOption) ? (int) $limitOption : 100;
        $dryRun = (bool) $this->option('dry-run');

        $failedTransactions = $this->transactions->getFailedToReprocess();

        if ($failedTransactions->isEmpty()) {
            $this->info('No failed transactions to reprocess.');

            return 0;
        }

        $failedTransactions = $failedTransactions->take($limit);

        $this->info(sprintf('Found %d failed transactions to reprocess (limit: %d).', $failedTransactions->count(), $limit));

        foreach ($failedTransactions as $failedTransaction) {
            $this->info('Reprocessing transaction ID: '.$failedTransaction->id);

            if ($dryRun) {
                $this->line('Dry-run: would reprocess transaction '.$failedTransaction->id);
                continue;
            }

            try {
                $payload = is_array($failedTransaction->metadata) ? $failedTransaction->metadata : [];
                $this->paymentService->processPayment($payload);
                $this->info(sprintf('Transaction ID: %d reprocessed successfully.', $failedTransaction->id));
            } catch (\Throwable $e) {
                $this->error(sprintf('Failed to reprocess transaction ID: %d. Error: %s', $failedTransaction->id, $e->getMessage()));
            }
        }

        $this->info('Finished reprocessing failed payments.');

        return 0;
    }
}
