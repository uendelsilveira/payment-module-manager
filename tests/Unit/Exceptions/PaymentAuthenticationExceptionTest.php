<?php

namespace UendelSilveira\PaymentModuleManager\Tests\Unit\Exceptions;

use UendelSilveira\PaymentModuleManager\Exceptions\PaymentAuthenticationException;
use UendelSilveira\PaymentModuleManager\Tests\TestCase;

class PaymentAuthenticationExceptionTest extends TestCase
{
    public function test_exception_can_be_instantiated_with_default_values(): void
    {
        // Act
        $exception = new PaymentAuthenticationException();

        // Assert
        $this->assertInstanceOf(PaymentAuthenticationException::class, $exception);
        $this->assertEquals('Não autenticado', $exception->getMessage());
        $this->assertEquals(401, $exception->getStatusCode());
    }

    public function test_exception_can_be_instantiated_with_custom_values(): void
    {
        // Arrange
        $message = 'Credenciais inválidas';
        $statusCode = 403;

        // Act
        $exception = new PaymentAuthenticationException($message, $statusCode);

        // Assert
        $this->assertInstanceOf(PaymentAuthenticationException::class, $exception);
        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($statusCode, $exception->getStatusCode());
    }
}
