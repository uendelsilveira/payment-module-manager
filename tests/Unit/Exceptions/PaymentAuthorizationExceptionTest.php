<?php

namespace UendelSilveira\PaymentModuleManager\Tests\Unit\Exceptions;

use UendelSilveira\PaymentModuleManager\Exceptions\PaymentAuthorizationException;
use UendelSilveira\PaymentModuleManager\Tests\TestCase;

class PaymentAuthorizationExceptionTest extends TestCase
{
    public function test_exception_can_be_instantiated_with_default_values(): void
    {
        // Act
        $exception = new PaymentAuthorizationException();

        // Assert
        $this->assertInstanceOf(PaymentAuthorizationException::class, $exception);
        $this->assertEquals('You do not have permission for this action', $exception->getMessage());
        $this->assertEquals(403, $exception->getStatusCode());
    }

    public function test_exception_can_be_instantiated_with_custom_values(): void
    {
        // Arrange
        $message = 'Acesso negado';
        $statusCode = 404;

        // Act
        $exception = new PaymentAuthorizationException($message, $statusCode);

        // Assert
        $this->assertInstanceOf(PaymentAuthorizationException::class, $exception);
        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($statusCode, $exception->getStatusCode());
    }
}
