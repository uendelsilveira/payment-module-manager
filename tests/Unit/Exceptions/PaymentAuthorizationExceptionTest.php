<?php

namespace UendelSilveira\PaymentModuleManager\Tests\Unit\Exceptions;

use UendelSilveira\PaymentModuleManager\Exceptions\PaymentAuthorizationException;
use UendelSilveira\PaymentModuleManager\Tests\TestCase;

class PaymentAuthorizationExceptionTest extends TestCase
{
    public function test_exception_can_be_instantiated_with_default_values(): void
    {
        // Act
        $paymentAuthorizationException = new PaymentAuthorizationException;

        // Assert
        $this->assertInstanceOf(PaymentAuthorizationException::class, $paymentAuthorizationException);
        $this->assertEquals('You do not have permission for this action', $paymentAuthorizationException->getMessage());
        $this->assertEquals(403, $paymentAuthorizationException->getStatusCode());
    }

    public function test_exception_can_be_instantiated_with_custom_values(): void
    {
        // Arrange
        $message = 'Acesso negado';
        $statusCode = 404;

        // Act
        $paymentAuthorizationException = new PaymentAuthorizationException($message, $statusCode);

        // Assert
        $this->assertInstanceOf(PaymentAuthorizationException::class, $paymentAuthorizationException);
        $this->assertEquals($message, $paymentAuthorizationException->getMessage());
        $this->assertEquals($statusCode, $paymentAuthorizationException->getStatusCode());
    }
}
