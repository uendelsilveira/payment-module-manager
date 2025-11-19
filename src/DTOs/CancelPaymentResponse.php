<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 19/11/2025 14:24:12
*/

namespace UendelSilveira\PaymentModuleManager\DTOs;

use UendelSilveira\PaymentModuleManager\Enums\PaymentStatus;

class CancelPaymentResponse
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
