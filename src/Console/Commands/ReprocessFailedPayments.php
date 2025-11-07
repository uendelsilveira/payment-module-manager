<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 04/11/2025 16:09:38
*/

namespace UendelSilveira\PaymentModuleManager\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use UendelSilveira\PaymentModuleManager\Models\Transaction;
use UendelSilveira\PaymentModuleManager\Services\PaymentService;
use UendelSilveira\PaymentModuleManager\Support\LogContext;

class ReprocessFailedPayments extends Command
{
    protected $signature = 'payment:reprocess-failed
                            {--gateway= : Filter by specific gateway (e.g., mercadopago)}
                            {--max-retries=3 : Only reprocess transactions with retries less than this value}
                            {--age= : Only reprocess transactions older than X minutes (default: 5)}
                            {--limit= : Maximum number of transactions to reprocess}
                            {--dry-run : Show what would be reprocessed without actually doing it}
                            {--force : Force reprocess even if max retries reached}';

    protected $description = 'Reprocess failed payment transactions with advanced filtering and dry-run support';

    public function __construct(protected PaymentService $paymentService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $startTime = microtime(true);
        $dryRun = $this->option('dry-run');

        $logContext = LogContext::create()
            ->withCorrelationId()
            ->with('dry_run', $dryRun);

        $message = $dryRun ? 'Starting failed payments reprocessing command (DRY RUN)' : 'Starting failed payments reprocessing command';
        Log::channel('payment')->info($message, $logContext->toArray());

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No transactions will be actually reprocessed');
        }

        $transactions = $this->buildQuery();

        if ($transactions->isEmpty()) {
            $this->info('✅ No failed transactions found matching the criteria');
            Log::channel('payment')->info('No failed transactions found for reprocessing', $logContext->toArray());

            return Command::SUCCESS;
        }

        $logContext->with('total_transactions', $transactions->count());
        $this->line('');
        $this->info(sprintf('📋 Found %s transaction(s) to reprocess:', $transactions->count()));

        // Display transaction summary
        $this->displayTransactionSummary($transactions);
        $this->line('');

        if ($dryRun) {
            $this->info('✅ Dry run completed - no changes were made');

            return Command::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Do you want to proceed with reprocessing?', true)) {
            $this->warn('⚠️  Operation cancelled by user');

            return Command::SUCCESS;
        }

        $this->line('');
        $this->info('🔄 Starting reprocessing...');

        $hasFailures = false;
        $successCount = 0;
        $failureCount = 0;

        foreach ($transactions as $index => $transaction) {
            $txContext = clone $logContext;
            $txContext->withTransaction($transaction);

            $this->line("\n[".($index + 1).sprintf('/%s] Processing transaction #%s...', $transactions->count(), $transaction->id));

            try {
                $result = $this->paymentService->reprocess($transaction);
                $successCount++;

                $statusIcon = $result->status === 'approved' ? '✅' : '⏳';
                $this->info(sprintf('%s Transaction #%s reprocessed - Status: %s', $statusIcon, $transaction->id, $result->status));
            } catch (\Throwable $e) {
                $hasFailures = true;
                $failureCount++;
                $txContext->withError($e);
                Log::channel('payment')->error('Command reprocessing failed', $txContext->toArray());
                $this->error(sprintf('❌ Failed to reprocess transaction #%s: %s', $transaction->id, $e->getMessage()));
            }
        }

        $logContext->withDuration($startTime)
            ->with('success_count', $successCount)
            ->with('failure_count', $failureCount);

        $this->line('');
        $this->displaySummary($successCount, $failureCount, $startTime);

        Log::channel('payment')->info('Failed payments reprocessing command completed', $logContext->toArray());

        return $hasFailures ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Build query with filters from options
     *
     * @return \Illuminate\Support\Collection<int, Transaction>
     */
    private function buildQuery(): \Illuminate\Support\Collection
    {
        $builder = Transaction::query()->where('status', 'failed');

        // Filter by gateway
        $gateway = $this->option('gateway');

        if (is_string($gateway) && $gateway !== '') {
            $builder->where('gateway', $gateway);
            $this->line('🔍 Filtering by gateway: '.$gateway);
        }

        // Filter by max retries
        $maxRetries = (int) $this->option('max-retries');

        if (! $this->option('force')) {
            $builder->where('retries_count', '<', $maxRetries);
            $this->line('🔍 Filtering by retries < '.$maxRetries);
        }

        // Filter by age (last attempt)
        $ageMinutes = (int) ($this->option('age') ?? 5);
        $builder->where(function ($q) use ($ageMinutes): void {
            $q->whereNull('last_attempt_at')
                ->orWhere('last_attempt_at', '<', now()->subMinutes($ageMinutes));
        });
        $this->line(sprintf('🔍 Filtering transactions older than %d minutes', $ageMinutes));

        // Apply limit
        $limit = $this->option('limit');

        if ($limit !== null && is_numeric($limit)) {
            $builder->limit((int) $limit);
            $this->line(sprintf('🔍 Limiting to %d transaction(s)', (int) $limit));
        }

        /** @var \Illuminate\Support\Collection<int, Transaction> */
        return $builder->orderBy('created_at', 'asc')->get();
    }

    /**
     * Display transaction summary table
     *
     * @param \Illuminate\Support\Collection<int, Transaction> $transactions
     */
    private function displayTransactionSummary(\Illuminate\Support\Collection $transactions): void
    {
        $headers = ['ID', 'Gateway', 'Amount', 'Retries', 'Last Attempt', 'Created At'];
        $rows = [];

        foreach ($transactions as $transaction) {
            $rows[] = [
                $transaction->id,
                $transaction->gateway,
                number_format($transaction->amount, 2),
                $transaction->retries_count ?? 0,
                $transaction->last_attempt_at ? $transaction->last_attempt_at->diffForHumans() : 'Never',
                $transaction->created_at ? $transaction->created_at->diffForHumans() : 'N/A',
            ];
        }

        $this->table($headers, $rows);
    }

    /**
     * Display final summary
     */
    private function displaySummary(int $successCount, int $failureCount, float $startTime): void
    {
        $totalTime = round(microtime(true) - $startTime, 2);
        $total = $successCount + $failureCount;

        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('  📊 REPROCESSING SUMMARY');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line('  Total processed:   '.$total);
        $this->line('  ✅ Successful:      '.$successCount);

        if ($failureCount > 0) {
            $this->line('  ❌ Failed:          '.$failureCount);
        }

        $this->line(sprintf('  ⏱️  Time elapsed:    %ss', $totalTime));
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        if ($failureCount === 0 && $successCount > 0) {
            $this->info("\n🎉 All transactions reprocessed successfully!");
        } elseif ($failureCount > 0) {
            $this->warn("\n⚠️  Some transactions failed to reprocess. Check logs for details.");
        }
    }
}
