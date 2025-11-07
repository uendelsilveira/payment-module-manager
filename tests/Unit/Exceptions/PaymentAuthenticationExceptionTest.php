<?php

namespace UendelSilveira\PaymentModuleManager\Tests\Unit\Exceptions;

use UendelSilveira\PaymentModuleManager\Exceptions\PaymentAuthenticationException;
use UendelSilveira\PaymentModuleManager\Tests\TestCase;

class PaymentAuthenticationExceptionTest extends TestCase
{
    public function test_exception_can_be_instantiated_with_default_values(): void
    {
        // Act
        $paymentAuthenticationException = new PaymentAuthenticationException;

        // Assert
        $this->assertInstanceOf(PaymentAuthenticationException::class, $paymentAuthenticationException);
        $this->assertEquals('Não autenticado', $paymentAuthenticationException->getMessage());
        $this->assertEquals(401, $paymentAuthenticationException->getStatusCode());
    }

    public function test_exception_can_be_instantiated_with_custom_values(): void
    {
        // Arrange
        $message = 'Credenciais inválidas';
        $statusCode = 403;

        // Act
        $paymentAuthenticationException = new PaymentAuthenticationException($message, $statusCode);

        // Assert
        $this->assertInstanceOf(PaymentAuthenticationException::class, $paymentAuthenticationException);
        $this->assertEquals($message, $paymentAuthenticationException->getMessage());
        $this->assertEquals($statusCode, $paymentAuthenticationException->getStatusCode());
    }
}
