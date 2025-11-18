<?php

namespace UendelSilveira\PaymentModuleManager\DTOs;

use UendelSilveira\PaymentModuleManager\Enums\PaymentStatus;

class RefundPaymentResponse
{
    /**
     * @param array<string, mixed> $details
     */
    public function __construct(
        public string $refundId,
        public string $transactionId,
        public PaymentStatus $status,
        public float $amount,
        public array $details = []
    ) {}
}
