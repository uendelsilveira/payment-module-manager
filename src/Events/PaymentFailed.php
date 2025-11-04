<?php

namespace UendelSilveira\PaymentModuleManager\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Throwable;
use UendelSilveira\PaymentModuleManager\Models\Transaction;

/**
 * Event fired when a payment processing fails
 */
class PaymentFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Transaction $transaction,
        public readonly Throwable $exception,
        public readonly array $paymentData
    ) {}
}
