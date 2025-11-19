<?php

namespace UendelSilveira\PaymentModuleManager\Listeners;

use Illuminate\Support\Facades\Log;
use UendelSilveira\PaymentModuleManager\Events\PaymentFailed;
use UendelSilveira\PaymentModuleManager\Support\LogContext;

class LogPaymentFailed
{
    public function handle(PaymentFailed $paymentFailed): void
    {
        $logContext = LogContext::create()
            ->withCorrelationId()
            ->withError($paymentFailed->exception)
            ->with('payment_data', $paymentFailed->paymentData)
            ->maskSensitiveData();

        Log::channel('payment')->error(
            'Payment processing failed via event',
            $logContext->toArray()
        );
    }
}
