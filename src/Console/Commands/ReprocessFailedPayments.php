<?php

namespace UendelSilveira\PaymentModuleManager\Console\Commands;

use Illuminate\Console\Command;
use UendelSilveira\PaymentModuleManager\Models\Transaction;
use UendelSilveira\PaymentModuleManager\Services\PaymentService;

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
        $transactions = Transaction::where('status', 'failed')->get();

        if ($transactions->isEmpty()) {
            $this->info('Nenhuma transação falha para reprocessar encontrada.');

            return Command::SUCCESS;
        }

        $hasFailures = false;

        foreach ($transactions as $transaction) {
            $this->info("Reprocessando transação ID: {$transaction->id}...");

            try {
                $this->paymentService->reprocess($transaction);
                $this->info("Transação ID: {$transaction->id} reprocessada com sucesso.");
            } catch (\Throwable $e) {
                $hasFailures = true;
                $this->info("Falha ao reprocessar transação ID: {$transaction->id}. Erro: {$e->getMessage()}");
            }
        }

        return $hasFailures ? Command::FAILURE : Command::SUCCESS;
    }
}
