<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 04/11/2025 16:09:38
*/

namespace UendelSilveira\PaymentModuleManager\Listeners;

use Illuminate\Support\Facades\Log;
use UendelSilveira\PaymentModuleManager\Events\PaymentFailed;
use UendelSilveira\PaymentModuleManager\Support\LogContext;

/**
 * Listener to log failed payment processing
 */
class LogPaymentFailed
{
    public function handle(PaymentFailed $paymentFailed): void
    {
        $logContext = LogContext::create()
            ->withCorrelationId()
            ->withTransaction($paymentFailed->transaction)
            ->withError($paymentFailed->exception)
            ->with('payment_data', $paymentFailed->paymentData)
            ->maskSensitiveData();

        Log::channel('payment')->error(
            'Payment processing failed via event',
            $logContext->toArray()
        );
    }
}
