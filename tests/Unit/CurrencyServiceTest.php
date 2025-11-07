<?php

namespace UendelSilveira\PaymentModuleManager\Tests\Unit;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Orchestra\Testbench\TestCase;
use UendelSilveira\PaymentModuleManager\Exceptions\InvalidConfigurationException;
use UendelSilveira\PaymentModuleManager\Services\CurrencyService;

class CurrencyServiceTest extends TestCase
{
    private CurrencyService $currencyService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->currencyService = new CurrencyService;

        // Set up default currency configuration
        Config::set('payment.currencies.default', 'BRL');
        Config::set('payment.currencies.supported', [
            'BRL' => [
                'name' => 'Brazilian Real',
                'symbol' => 'R$',
                'decimal_places' => 2,
            ],
            'USD' => [
                'name' => 'US Dollar',
                'symbol' => '$',
                'decimal_places' => 2,
            ],
            'EUR' => [
                'name' => 'Euro',
                'symbol' => '€',
                'decimal_places' => 2,
            ],
        ]);
    }

    public function test_get_supported_currencies_returns_array(): void
    {
        $currencies = $this->currencyService->getSupportedCurrencies();

        $this->assertArrayHasKey('BRL', $currencies);
        $this->assertArrayHasKey('USD', $currencies);
        $this->assertArrayHasKey('EUR', $currencies);
    }

    public function test_get_supported_currencies_returns_empty_array_when_not_configured(): void
    {
        Config::set('payment.currencies.supported', null);

        $currencies = $this->currencyService->getSupportedCurrencies();

        $this->assertEmpty($currencies);
    }

    public function test_get_default_currency_returns_configured_value(): void
    {
        $currency = $this->currencyService->getDefaultCurrency();

        $this->assertEquals('BRL', $currency);
    }

    public function test_get_default_currency_returns_brl_when_not_configured(): void
    {
        Config::set('payment.currencies.default', null);

        $currency = $this->currencyService->getDefaultCurrency();

        $this->assertEquals('BRL', $currency);
    }

    public function test_is_supported_returns_true_for_valid_currency(): void
    {
        $this->assertTrue($this->currencyService->isSupported('BRL'));
        $this->assertTrue($this->currencyService->isSupported('USD'));
        $this->assertTrue($this->currencyService->isSupported('EUR'));
    }

    public function test_is_supported_returns_false_for_invalid_currency(): void
    {
        $this->assertFalse($this->currencyService->isSupported('GBP'));
        $this->assertFalse($this->currencyService->isSupported('JPY'));
    }

    public function test_validate_passes_for_supported_currency(): void
    {
        $this->expectNotToPerformAssertions();

        $this->currencyService->validate('BRL');
        $this->currencyService->validate('USD');
    }

    public function test_validate_throws_exception_for_unsupported_currency(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Currency GBP is not supported');

        $this->currencyService->validate('GBP');
    }

    public function test_get_currency_details_returns_array_for_valid_currency(): void
    {
        $details = $this->currencyService->getCurrencyDetails('BRL');

        $this->assertIsArray($details);
        $this->assertEquals('Brazilian Real', $details['name']);
        $this->assertEquals('R$', $details['symbol']);
        $this->assertEquals(2, $details['decimal_places']);
    }

    public function test_get_currency_details_returns_null_for_invalid_currency(): void
    {
        $details = $this->currencyService->getCurrencyDetails('GBP');

        $this->assertNull($details);
    }

    public function test_format_returns_formatted_amount_with_symbol(): void
    {
        $formatted = $this->currencyService->format(100.50, 'BRL');

        $this->assertEquals('R$ 100.50', $formatted);
    }

    public function test_format_returns_formatted_amount_with_usd(): void
    {
        $formatted = $this->currencyService->format(99.99, 'USD');

        $this->assertEquals('$ 99.99', $formatted);
    }

    public function test_format_returns_simple_format_for_invalid_currency(): void
    {
        $formatted = $this->currencyService->format(100.50, 'INVALID');

        $this->assertEquals('100.50', $formatted);
    }

    public function test_format_respects_decimal_places(): void
    {
        Config::set('payment.currencies.supported.JPY', [
            'name' => 'Japanese Yen',
            'symbol' => '¥',
            'decimal_places' => 0,
        ]);

        $formatted = $this->currencyService->format(1000, 'JPY');

        $this->assertEquals('¥ 1,000', $formatted);
    }

    public function test_convert_returns_same_amount_for_same_currency(): void
    {
        $converted = $this->currencyService->convert(100.00, 'BRL', 'BRL');

        $this->assertEquals(100.00, $converted);
    }

    public function test_convert_throws_exception_when_conversion_disabled(): void
    {
        Config::set('payment.currencies.conversion.enabled', false);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Currency conversion is not enabled');

        $this->currencyService->convert(100.00, 'BRL', 'USD');
    }

    public function test_convert_converts_amount_between_currencies(): void
    {
        Config::set('payment.currencies.conversion.enabled', true);
        Cache::flush();

        $converted = $this->currencyService->convert(100.00, 'BRL', 'USD');

        // Based on the hardcoded rate BRL_USD = 0.20
        $this->assertEquals(20.00, $converted);
    }

    public function test_convert_uses_cached_exchange_rate(): void
    {
        Config::set('payment.currencies.conversion.enabled', true);
        Cache::flush();

        // First call - should cache the rate
        $this->currencyService->convert(100.00, 'USD', 'BRL');

        // Verify cache was used
        $cacheKey = 'exchange_rate:USD:BRL';
        $this->assertTrue(Cache::has($cacheKey));
        $this->assertEquals(5.00, Cache::get($cacheKey));
    }

    public function test_get_decimal_places_returns_correct_value(): void
    {
        $decimalPlaces = $this->currencyService->getDecimalPlaces('BRL');

        $this->assertEquals(2, $decimalPlaces);
    }

    public function test_get_decimal_places_returns_default_for_invalid_currency(): void
    {
        $decimalPlaces = $this->currencyService->getDecimalPlaces('INVALID');

        $this->assertEquals(2, $decimalPlaces);
    }

    public function test_get_decimal_places_with_custom_value(): void
    {
        Config::set('payment.currencies.supported.JPY', [
            'name' => 'Japanese Yen',
            'symbol' => '¥',
            'decimal_places' => 0,
        ]);

        $decimalPlaces = $this->currencyService->getDecimalPlaces('JPY');

        $this->assertEquals(0, $decimalPlaces);
    }
}
