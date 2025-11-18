<?php

namespace UendelSilveira\PaymentModuleManager\DTOs;

use UendelSilveira\PaymentModuleManager\Enums\PaymentStatus;

class ProcessPaymentResponse
{
    /**
     * @param array<string, mixed> $details
     */
    public function __construct(
        public string $transactionId,
        public PaymentStatus $status,
        public array $details = []
    ) {}
}
