<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created at: 11/11/25
*/

namespace UendelSilveira\PaymentModuleManager\Gateways;

class RefundClient
{
    public function refund(int $transactionId, array $data): array
    {
        return [
            'id' => $transactionId,
            'payment_id' => $transactionId,
            'amount' => $data['amount'] ?? 0,
            'status' => 'refunded',
            'date_created' => now(),
        ];
    }
}
