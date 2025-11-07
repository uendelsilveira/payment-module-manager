<?php

namespace UendelSilveira\PaymentModuleManager\Tests\Unit\Events;

use UendelSilveira\PaymentModuleManager\Events\PaymentRefunded;
use UendelSilveira\PaymentModuleManager\Models\Transaction;
use UendelSilveira\PaymentModuleManager\Tests\TestCase;

class PaymentRefundedEventTest extends TestCase
{
    public function test_event_is_created_with_correct_data(): void
    {
        // Arrange
        $transaction = $this->createMock(Transaction::class);
        $refundData = ['id' => 'refund_123', 'amount' => 100.0];

        // Act
        $paymentRefunded = new PaymentRefunded($transaction, $refundData);

        // Assert
        $this->assertSame($transaction, $paymentRefunded->transaction);
        $this->assertEquals($refundData, $paymentRefunded->refundData);
    }
}
