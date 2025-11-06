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

        $min = is_numeric($limits['min'] ?? null) ? (float) $limits['min'] : 100;
        $max = is_numeric($limits['max'] ?? null) ? (float) $limits['max'] : 10000000;

        if ($amount < $min) {
            throw new PaymentGatewayException(
                sprintf(
                    'Transaction amount %.2f is below minimum allowed: %.2f for %s via %s',
                    $amount,
                    $min,
                    $paymentMethod ?? 'default method',
                    $gateway
                )
            );
        }

        if ($amount > $max) {
            throw new PaymentGatewayException(
                sprintf(
                    'Transaction amount %.2f exceeds maximum allowed: %.2f for %s via %s',
                    $amount,
                    $max,
                    $paymentMethod ?? 'default method',
                    $gateway
                )
            );
        }
    }

    /**
     * Get limits for specific gateway and payment method
     *
     * @return array<string, mixed>
     */
    public function getLimits(string $gateway, ?string $paymentMethod = null): array
    {
        $config = config('payment.monetary_limits', []);

        if (! is_array($config)) {
            return ['min' => 100, 'max' => 10000000];
        }

        // Try to get specific gateway + payment method limits
        if ($paymentMethod && isset($config[$gateway][$paymentMethod]) && is_array($config[$gateway][$paymentMethod])) {
            return $config[$gateway][$paymentMethod];
        }

        // Fall back to gateway default
        if (isset($config[$gateway]['default']) && is_array($config[$gateway]['default'])) {
            return $config[$gateway]['default'];
        }

        // Fall back to global limits
        if (isset($config['global']) && is_array($config['global'])) {
            return $config['global'];
        }

        return ['min' => 100, 'max' => 10000000];
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
