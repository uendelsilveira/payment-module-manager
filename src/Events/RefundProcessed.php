<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 19/11/2025 14:24:12
*/

namespace UendelSilveira\PaymentModuleManager\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use UendelSilveira\PaymentModuleManager\Models\Transaction;

class RefundProcessed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Transaction $transaction,
        public float $amount
    ) {}
}
