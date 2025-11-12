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
        $config = is_array($config) ? $config : [];

        // Try to get specific gateway + payment method limits
        if ($paymentMethod && is_string($paymentMethod)) {
            $gatewayConfig = is_array($config[$gateway] ?? null) ? $config[$gateway] : [];
            $methodConfig = is_array($gatewayConfig[$paymentMethod] ?? null) ? $gatewayConfig[$paymentMethod] : [];

            if ($methodConfig !== []) {
                return $methodConfig;
            }
        }

        // Fall back to gateway default
        $gatewayConfig = is_array($config[$gateway] ?? null) ? $config[$gateway] : [];
        $defaultConfig = is_array($gatewayConfig['default'] ?? null) ? $gatewayConfig['default'] : [];

        if ($defaultConfig !== []) {
            return $defaultConfig;
        }

        // Fall back to global limits
        $globalConfig = is_array($config['global'] ?? null) ? $config['global'] : [];

        if ($globalConfig !== []) {
            return $globalConfig;
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
