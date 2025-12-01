<?php

declare(strict_types=1);

namespace UendelSilveira\PaymentModuleManager\Tests\Unit\DTO;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use UendelSilveira\PaymentModuleManager\DTOs\CardDetails;

final class CardDetailsTest extends TestCase
{
    public function test_can_create_valid_visa_card(): void
    {
        $card = CardDetails::fromArray([
            'number' => '4242424242424242',
            'exp_month' => 12,
            'exp_year' => 2025,
            'cvc' => '123',
        ]);

        $this->assertEquals('**** **** **** 4242', $card->getMaskedNumber());
        $this->assertEquals(12, $card->getExpMonth());
        $this->assertEquals(2025, $card->getExpYear());
        $this->assertEquals('123', $card->getCvc());
        $this->assertEquals('visa', $card->getCardBrand());
    }

    public function test_can_create_valid_mastercard_card(): void
    {
        $card = CardDetails::fromArray([
            'number' => '5555555555554444',
            'exp_month' => 6,
            'exp_year' => 2026,
            'cvc' => '456',
            'holder_name' => 'JOHN DOE',
        ]);

        $this->assertEquals('**** **** **** 4444', $card->getMaskedNumber());
        $this->assertEquals('mastercard', $card->getCardBrand());
        $this->assertEquals('JOHN DOE', $card->getHolderName());
    }

    public function test_rejects_invalid_card_number(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid card number');

        CardDetails::fromArray([
            'number' => '1234567890123456', // Falha no algoritmo de Luhn
            'exp_month' => 12,
            'exp_year' => 2025,
            'cvc' => '123',
        ]);
    }

    public function test_rejects_expired_card(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Card has expired');

        CardDetails::fromArray([
            'number' => '4242424242424242',
            'exp_month' => 12,
            'exp_year' => 2020,
            'cvc' => '123',
        ]);
    }

    public function test_rejects_invalid_expiration_month(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid expiration month');

        CardDetails::fromArray([
            'number' => '4242424242424242',
            'exp_month' => 13,
            'exp_year' => 2025,
            'cvc' => '123',
        ]);
    }

    public function test_rejects_invalid_cvc(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid CVC');

        CardDetails::fromArray([
            'number' => '4242424242424242',
            'exp_month' => 12,
            'exp_year' => 2025,
            'cvc' => '12', // CVC muito curto
        ]);
    }

    public function test_serialization_does_not_expose_sensitive_data(): void
    {
        $card = CardDetails::fromArray([
            'number' => '4242424242424242',
            'exp_month' => 12,
            'exp_year' => 2025,
            'cvc' => '123',
        ]);

        $serialized = serialize($card);

        // Não deve conter o número completo do cartão
        $this->assertStringNotContainsString('4242424242424242', $serialized);

        // Não deve conter o CVC
        $this->assertStringNotContainsString('"cvc"', $serialized);

        // Deve conter apenas dados mascarados
        $this->assertStringContainsString('4242', $serialized);
    }

    public function test_cannot_unserialize_card_details(): void
    {
        $card = CardDetails::fromArray([
            'number' => '4242424242424242',
            'exp_month' => 12,
            'exp_year' => 2025,
            'cvc' => '123',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('CardDetails cannot be unserialized');

        unserialize(serialize($card));
    }
}    /*
     By Uendel Silveira
     Developer Web
     IDE: PhpStorm
     Created at: 24/11/25
    */
