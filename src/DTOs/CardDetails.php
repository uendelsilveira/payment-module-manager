<?php

/*
 * By Uendel Silveira
 * Developer Web
 * IDE: PhpStorm
 * Created at: 24/11/25
 */

declare(strict_types=1);

namespace UendelSilveira\PaymentModuleManager\DTOs;

use InvalidArgumentException;

final readonly class CardDetails
{
    private function __construct(
        private string $number,
        private int $expMonth,
        private int $expYear,
        private string $cvc,
        private ?string $holderName = null,
    ) {
        $this->validate();
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        foreach (['number', 'exp_month', 'exp_year', 'cvc'] as $field) {
            if (! isset($data[$field])) {
                throw new InvalidArgumentException("Card {$field} is required");
            }
        }

        if (! is_string($data['number']) && ! is_numeric($data['number'])) {
            throw new InvalidArgumentException('Card number must be a string or numeric.');
        }

        if (! is_numeric($data['exp_month'])) {
            throw new InvalidArgumentException('Expiration month must be numeric.');
        }

        if (! is_numeric($data['exp_year'])) {
            throw new InvalidArgumentException('Expiration year must be numeric.');
        }

        if (! is_string($data['cvc']) && ! is_numeric($data['cvc'])) {
            throw new InvalidArgumentException('CVC must be a string or numeric.');
        }

        if (isset($data['holder_name']) && ! is_string($data['holder_name'])) {
            throw new InvalidArgumentException('Holder name must be a string.');
        }

        $holderName = null;

        if (isset($data['holder_name']) && is_string($data['holder_name'])) {
            $holderName = $data['holder_name'];
        }

        return new self(
            number: (string) $data['number'],
            expMonth: (int) $data['exp_month'],
            expYear: (int) $data['exp_year'],
            cvc: (string) $data['cvc'],
            holderName: $holderName
        );
    }

    private function validate(): void
    {
        $cleanNumber = preg_replace('/\D/', '', $this->number);

        if ($cleanNumber === null || $cleanNumber === '') {
            throw new InvalidArgumentException('Invalid card number format');
        }

        if (! $this->isValidCardNumber($cleanNumber)) {
            throw new InvalidArgumentException('Invalid card number');
        }

        if ($this->expMonth < 1 || $this->expMonth > 12) {
            throw new InvalidArgumentException('Invalid expiration month');
        }

        $currentYear = (int) date('Y');
        $currentMonth = (int) date('m');

        if ($this->expYear < $currentYear || ($this->expYear === $currentYear && $this->expMonth < $currentMonth)) {
            throw new InvalidArgumentException('Card has expired');
        }

        if (! preg_match('/^\d{3,4}$/', $this->cvc)) {
            throw new InvalidArgumentException('Invalid CVC');
        }
    }

    private function isValidCardNumber(string $number): bool
    {
        $length = strlen($number);

        if ($length < 13 || $length > 19) {
            return false;
        }

        $sum = 0;
        $parity = $length % 2;

        for ($i = 0; $i < $length; $i++) {
            $digit = (int) $number[$i];

            if ($i % 2 === $parity) {
                $digit *= 2;

                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            $sum += $digit;
        }

        return $sum % 10 === 0;
    }

    public function getNumber(): string
    {
        return $this->number;
    }

    public function getExpMonth(): int
    {
        return $this->expMonth;
    }

    public function getExpYear(): int
    {
        return $this->expYear;
    }

    public function getCvc(): string
    {
        return $this->cvc;
    }

    public function getHolderName(): ?string
    {
        return $this->holderName;
    }

    public function getMaskedNumber(): string
    {
        $cleanNumber = (string) (preg_replace('/\D/', '', $this->number) ?? '');

        return '**** **** **** '.substr($cleanNumber, -4);
    }

    public function getCardBrand(): string
    {
        $number = preg_replace('/\D/', '', $this->number);

        if (empty($number)) {
            return 'unknown';
        }

        if (str_starts_with($number, '4')) {
            return 'visa';
        }

        if (preg_match('/^(5[1-5]|2[2-7])/', $number)) {
            return 'mastercard';
        }

        if (preg_match('/^3[47]/', $number)) {
            return 'amex';
        }

        if (preg_match('/^(4011|4312|4389|4514|4573|5041|5066|5090|6277|6362|6363|6504|6505|6516)/', $number)) {
            return 'elo';
        }

        if (preg_match('/^(38|60)/', $number)) {
            return 'hipercard';
        }

        return 'unknown';
    }

    /**
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        return [
            'masked_number' => $this->getMaskedNumber(),
            'exp_month' => $this->expMonth,
            'exp_year' => $this->expYear,
            'card_brand' => $this->getCardBrand(),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function __unserialize(array $data): void
    {
        throw new InvalidArgumentException('CardDetails cannot be unserialized for security reasons');
    }
}
