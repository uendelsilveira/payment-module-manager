<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 04/11/2025 16:09:38
*/

namespace UendelSilveira\PaymentModuleManager\Listeners;

use Illuminate\Support\Facades\Log;
use UendelSilveira\PaymentModuleManager\Events\PaymentStatusChanged;
use UendelSilveira\PaymentModuleManager\Support\LogContext;

/**
 * Listener to log payment status changes
 */
class LogPaymentStatusChanged
{
    public function handle(PaymentStatusChanged $event): void
    {
        $context = LogContext::create()
            ->withCorrelationId()
            ->withTransaction($event->transaction)
            ->with('old_status', $event->oldStatus)
            ->with('new_status', $event->newStatus);

        Log::channel('transaction')->info(
            'Payment status changed via event',
            $context->toArray()
        );
    }
}
