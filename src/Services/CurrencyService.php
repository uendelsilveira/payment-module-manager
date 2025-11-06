<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 04/11/2025 16:09:38
*/

namespace UendelSilveira\PaymentModuleManager\Services;

use Illuminate\Support\Facades\Cache;
use UendelSilveira\PaymentModuleManager\Exceptions\InvalidConfigurationException;

/**
 * Service for handling multi-currency operations
 */
class CurrencyService
{
    /**
     * Get list of supported currencies
     */
    public function getSupportedCurrencies(): array
    {
        return config('payment.currencies.supported', []);
    }

    /**
     * Get default currency
     */
    public function getDefaultCurrency(): string
    {
        return config('payment.currencies.default', 'BRL');
    }

    /**
     * Check if a currency is supported
     */
    public function isSupported(string $currency): bool
    {
        return isset(config('payment.currencies.supported')[$currency]);
    }

    /**
     * Validate currency code
     *
     * @throws InvalidConfigurationException
     */
    public function validate(string $currency): void
    {
        if (! $this->isSupported($currency)) {
            $supported = implode(', ', array_keys($this->getSupportedCurrencies()));

            throw new InvalidConfigurationException(
                sprintf('Currency %s is not supported. Supported currencies: %s', $currency, $supported)
            );
        }
    }

    /**
     * Get currency details
     */
    public function getCurrencyDetails(string $currency): ?array
    {
        return config('payment.currencies.supported.' . $currency);
    }

    /**
     * Format amount with currency symbol
     */
    public function format(float $amount, string $currency): string
    {
        $details = $this->getCurrencyDetails($currency);

        if (! $details) {
            return number_format($amount, 2);
        }

        $formatted = number_format($amount, $details['decimal_places']);

        return sprintf('%s %s', $details['symbol'], $formatted);
    }

    /**
     * Convert amount between currencies (simplified version)
     * In production, this should call an actual exchange rate API
     */
    public function convert(float $amount, string $from, string $to): float
    {
        if ($from === $to) {
            return $amount;
        }

        if (! config('payment.currencies.conversion.enabled', false)) {
            throw new InvalidConfigurationException(
                'Currency conversion is not enabled. Set PAYMENT_CURRENCY_CONVERSION=true'
            );
        }

        // Get exchange rate (from cache or API)
        $rate = $this->getExchangeRate($from, $to);

        return $amount * $rate;
    }

    /**
     * Get exchange rate between two currencies
     * This is a simplified version - in production use real API
     */
    private function getExchangeRate(string $from, string $to): float
    {
        $cacheKey = sprintf('exchange_rate:%s:%s', $from, $to);
        $cacheTTL = config('payment.currencies.conversion.cache_ttl', 60);

        return Cache::remember($cacheKey, $cacheTTL * 60, function () use ($from, $to): float {
            // Simplified static rates for demonstration
            // In production, call external API here
            $rates = [
                'BRL_USD' => 0.20,
                'USD_BRL' => 5.00,
                'BRL_EUR' => 0.18,
                'EUR_BRL' => 5.55,
                'USD_EUR' => 0.92,
                'EUR_USD' => 1.09,
                'BRL_ARS' => 39.50,
                'ARS_BRL' => 0.025,
            ];

            $key = sprintf('%s_%s', $from, $to);

            return $rates[$key] ?? 1.0;
        });
    }

    /**
     * Get decimal places for currency
     */
    public function getDecimalPlaces(string $currency): int
    {
        $details = $this->getCurrencyDetails($currency);

        return $details['decimal_places'] ?? 2;
    }
}
