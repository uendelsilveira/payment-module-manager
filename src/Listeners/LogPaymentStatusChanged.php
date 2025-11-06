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
    public function handle(PaymentStatusChanged $paymentStatusChanged): void
    {
        $logContext = LogContext::create()
            ->withCorrelationId()
            ->withTransaction($paymentStatusChanged->transaction)
            ->with('old_status', $paymentStatusChanged->oldStatus)
            ->with('new_status', $paymentStatusChanged->newStatus);

        Log::channel('transaction')->info(
            'Payment status changed via event',
            $logContext->toArray()
        );
    }
}
