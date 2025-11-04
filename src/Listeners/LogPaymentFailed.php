<?php

namespace UendelSilveira\PaymentModuleManager\Listeners;

use Illuminate\Support\Facades\Log;
use UendelSilveira\PaymentModuleManager\Events\PaymentFailed;
use UendelSilveira\PaymentModuleManager\Support\LogContext;

/**
 * Listener to log failed payment processing
 */
class LogPaymentFailed
{
    public function handle(PaymentFailed $event): void
    {
        $context = LogContext::create()
            ->withCorrelationId()
            ->withTransaction($event->transaction)
            ->withError($event->exception)
            ->with('payment_data', $event->paymentData)
            ->maskSensitiveData();

        Log::channel('payment')->error(
            'Payment processing failed via event',
            $context->toArray()
        );
    }
}
