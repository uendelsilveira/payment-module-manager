<?php

namespace UendelSilveira\PaymentModuleManager\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use UendelSilveira\PaymentModuleManager\Models\Transaction;

/**
 * Event fired when a payment status changes
 */
class PaymentStatusChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Transaction $transaction,
        public readonly string $oldStatus,
        public readonly string $newStatus
    ) {}
}
