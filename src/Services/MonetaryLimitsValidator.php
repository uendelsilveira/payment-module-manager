<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 04/11/2025 16:09:38
*/

namespace UendelSilveira\PaymentModuleManager\Services;

use UendelSilveira\PaymentModuleManager\Exceptions\PaymentGatewayException;

/**
 * Service to validate monetary limits for payment transactions
 */
class MonetaryLimitsValidator
{
    /**
     * Validate transaction amount against configured limits
     *
     * @throws PaymentGatewayException
     */
    public function validate(float $amount, string $gateway, ?string $paymentMethod = null): void
    {
        $limits = $this->getLimits($gateway, $paymentMethod);

        if ($amount < $limits['min']) {
            throw new PaymentGatewayException(
                sprintf(
                    'Transaction amount %.2f is below minimum allowed: %.2f for %s via %s',
                    $amount,
                    $limits['min'],
                    $paymentMethod ?? 'default method',
                    $gateway
                )
            );
        }

        if ($amount > $limits['max']) {
            throw new PaymentGatewayException(
                sprintf(
                    'Transaction amount %.2f exceeds maximum allowed: %.2f for %s via %s',
                    $amount,
                    $limits['max'],
                    $paymentMethod ?? 'default method',
                    $gateway
                )
            );
        }
    }

    /**
     * Get limits for specific gateway and payment method
     */
    public function getLimits(string $gateway, ?string $paymentMethod = null): array
    {
        $config = config('payment.monetary_limits', []);

        // Try to get specific gateway + payment method limits
        if (isset($config[$gateway][$paymentMethod])) {
            return $config[$gateway][$paymentMethod];
        }

        // Fall back to global limits
        return $config[$gateway]['default'] ?? $config['global'] ?? [
            'min' => 100, // R$ 1.00
            'max' => 10000000, // R$ 100,000.00
        ];
    }

    /**
     * Check if amount is within limits without throwing exception
     */
    public function isValid(float $amount, string $gateway, ?string $paymentMethod = null): bool
    {
        try {
            $this->validate($amount, $gateway, $paymentMethod);

            return true;
        } catch (PaymentGatewayException) {
            return false;
        }
    }

    /**
     * Get validation error message without throwing exception
     */
    public function getValidationError(float $amount, string $gateway, ?string $paymentMethod = null): ?string
    {
        try {
            $this->validate($amount, $gateway, $paymentMethod);

            return null;
        } catch (PaymentGatewayException $paymentGatewayException) {
            return $paymentGatewayException->getMessage();
        }
    }
}
