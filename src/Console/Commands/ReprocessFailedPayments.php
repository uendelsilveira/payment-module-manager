<?php

namespace UendelSilveira\PaymentModuleManager\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use UendelSilveira\PaymentModuleManager\Models\Transaction;
use UendelSilveira\PaymentModuleManager\Services\PaymentService;
use UendelSilveira\PaymentModuleManager\Support\LogContext;

class ReprocessFailedPayments extends Command
{
    protected $signature = 'payment:reprocess-failed';

    protected $description = 'Tenta reprocessar transações de pagamento que falharam.';

    protected PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        parent::__construct();
        $this->paymentService = $paymentService;
    }

    public function handle(): int
    {
        $startTime = microtime(true);
        $context = LogContext::create()->withCorrelationId();

        Log::channel('payment')->info('Starting failed payments reprocessing command', $context->toArray());

        $transactions = Transaction::where('status', 'failed')->get();

        if ($transactions->isEmpty()) {
            $this->info('Nenhuma transação falha para reprocessar encontrada.');
            Log::channel('payment')->info('No failed transactions found for reprocessing', $context->toArray());

            return Command::SUCCESS;
        }

        $context->with('total_transactions', $transactions->count());
        $this->info("Encontradas {$transactions->count()} transações para reprocessar.");

        $hasFailures = false;
        $successCount = 0;
        $failureCount = 0;

        foreach ($transactions as $transaction) {
            $txContext = clone $context;
            $txContext->withTransaction($transaction);

            $this->info("Reprocessando transação ID: {$transaction->id}...");

            try {
                $this->paymentService->reprocess($transaction);
                $successCount++;
                $this->info("Transação ID: {$transaction->id} reprocessada com sucesso.");
            } catch (\Throwable $e) {
                $hasFailures = true;
                $failureCount++;
                $txContext->withError($e);
                Log::channel('payment')->error('Command reprocessing failed', $txContext->toArray());
                $this->info("Falha ao reprocessar transação ID: {$transaction->id}. Erro: {$e->getMessage()}");
            }
        }

        $context->withDuration($startTime)
            ->with('success_count', $successCount)
            ->with('failure_count', $failureCount);

        Log::channel('payment')->info('Failed payments reprocessing command completed', $context->toArray());

        return $hasFailures ? Command::FAILURE : Command::SUCCESS;
    }
}
