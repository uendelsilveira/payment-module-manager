<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 04/11/2025 16:09:38
*/

namespace UendelSilveira\PaymentModuleManager\Listeners;

use Illuminate\Support\Facades\Log;
use UendelSilveira\PaymentModuleManager\Events\PaymentProcessed;
use UendelSilveira\PaymentModuleManager\Support\LogContext;

/**
 * Listener to log successful payment processing
 */
class LogPaymentProcessed
{
    public function handle(PaymentProcessed $event): void
    {
        $context = LogContext::create()
            ->withCorrelationId()
            ->withTransaction($event->transaction)
            ->with('gateway_response', $event->gatewayResponse)
            ->maskSensitiveData();

        Log::channel('payment')->info(
            'Payment processed successfully via event',
            $context->toArray()
        );
    }
}
