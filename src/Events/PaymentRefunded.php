<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 04/11/2025 16:09:38
*/

namespace UendelSilveira\PaymentModuleManager\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use UendelSilveira\PaymentModuleManager\Models\Transaction;

/**
 * Event fired when a payment is refunded
 */
class PaymentRefunded
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param array<string, mixed> $refundData
     */
    public function __construct(
        public readonly Transaction $transaction,
        public readonly array $refundData
    ) {}
}
