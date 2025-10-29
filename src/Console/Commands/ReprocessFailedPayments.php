<?php

namespace UendelSilveira\PaymentModuleManager\Console\Commands;

use Illuminate\Console\Command;
use UendelSilveira\PaymentModuleManager\Services\PaymentService;

class ReprocessFailedPayments extends Command
{
    protected $signature = 'payment:reprocess-failed';

    protected $description = 'Reprocess failed payments';

    protected PaymentService $paymentService;

    // Recebe via DI
    public function __construct(PaymentService $paymentService)
    {
        parent::__construct();
        $this->paymentService = $paymentService;
    }

    public function handle()
    {
        $failedTransactions = $this->paymentService->getFailedTransactions();

        foreach ($failedTransactions as $transaction) {
            $this->paymentService->reprocess($transaction);
        }

        return 0;
    }
}
