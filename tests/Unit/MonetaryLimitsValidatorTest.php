<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 04/11/2025 16:09:38
*/

namespace UendelSilveira\PaymentModuleManager\Tests\Unit;

use UendelSilveira\PaymentModuleManager\Exceptions\PaymentGatewayException;
use UendelSilveira\PaymentModuleManager\Services\MonetaryLimitsValidator;
use UendelSilveira\PaymentModuleManager\Tests\TestCase;

class MonetaryLimitsValidatorTest extends TestCase
{
    private MonetaryLimitsValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new MonetaryLimitsValidator;
    }

    public function test_validates_amount_within_limits(): void
    {
        $this->validator->validate(100.00, 'mercadopago', 'pix');
        $this->assertTrue(true); // No exception thrown
    }

    public function test_throws_exception_for_amount_below_minimum(): void
    {
        $this->expectException(PaymentGatewayException::class);
        $this->expectExceptionMessageMatches('/below minimum/');

        // PIX min is 0.01 (1 cent)
        $this->validator->validate(0.001, 'mercadopago', 'pix');
    }

    public function test_throws_exception_for_amount_above_maximum(): void
    {
        $this->expectException(PaymentGatewayException::class);
        $this->expectExceptionMessageMatches('/exceeds maximum/');

        // PIX max is R$ 10,000.00, so pass R$ 10,001.00
        $this->validator->validate(1000001.00, 'mercadopago', 'pix');
    }

    public function test_get_limits_for_specific_payment_method(): void
    {
        $limits = $this->validator->getLimits('mercadopago', 'pix');

        $this->assertEquals(1, $limits['min']);
        $this->assertEquals(1000000, $limits['max']);
    }

    public function test_falls_back_to_gateway_default_when_method_not_found(): void
    {
        $limits = $this->validator->getLimits('mercadopago', 'unknown_method');

        $this->assertEquals(100, $limits['min']);
        $this->assertEquals(10000000, $limits['max']);
    }

    public function test_falls_back_to_global_when_gateway_not_found(): void
    {
        $limits = $this->validator->getLimits('unknown_gateway');

        $this->assertEquals(100, $limits['min']);
        $this->assertEquals(10000000, $limits['max']);
    }

    public function test_is_valid_returns_true_for_valid_amount(): void
    {
        $this->assertTrue($this->validator->isValid(100.00, 'mercadopago', 'pix'));
    }

    public function test_is_valid_returns_false_for_invalid_amount(): void
    {
        $this->assertFalse($this->validator->isValid(0.001, 'mercadopago', 'pix'));
        $this->assertFalse($this->validator->isValid(1000001.00, 'mercadopago', 'pix'));
    }

    public function test_get_validation_error_returns_null_for_valid_amount(): void
    {
        $error = $this->validator->getValidationError(100.00, 'mercadopago', 'pix');
        $this->assertNull($error);
    }

    public function test_get_validation_error_returns_message_for_invalid_amount(): void
    {
        $error = $this->validator->getValidationError(0.001, 'mercadopago', 'pix');
        $this->assertNotNull($error);
        $this->assertStringContainsString('below minimum', $error);
    }
}
