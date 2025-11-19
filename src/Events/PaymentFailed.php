<?php

namespace UendelSilveira\PaymentModuleManager\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentFailed
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param array<string, mixed> $paymentData
     */
    public function __construct(
        public array $paymentData,
        public \Throwable $exception
    ) {}
}
