<?php

namespace UendelSilveira\PaymentModuleManager\Listeners;

use Illuminate\Support\Facades\Log;
use UendelSilveira\PaymentModuleManager\Events\PaymentProcessed;
use UendelSilveira\PaymentModuleManager\Support\LogContext;

class LogPaymentProcessed
{
    public function handle(PaymentProcessed $paymentProcessed): void
    {
        $logContext = LogContext::create()
            ->withCorrelationId()
            ->withTransaction($paymentProcessed->transaction)
            ->maskSensitiveData();

        Log::channel('payment')->info(
            'Payment processed successfully via event',
            $logContext->toArray()
        );
    }
}
