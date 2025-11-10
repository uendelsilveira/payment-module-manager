<?php

namespace UendelSilveira\PaymentModuleManager\DTOs;

use InvalidArgumentException;

final readonly class MercadoPagoTokenResponse
{
    public string $accessToken;

    public string $publicKey;

    public string $refreshToken;

    public int $userId;

    /**
     * @param array<string, mixed> $data
     */
    private function __construct(array $data)
    {
        $accessToken = $data['access_token'] ?? throw new InvalidArgumentException('access_token não encontrado na resposta da API.');
        $publicKey = $data['public_key'] ?? throw new InvalidArgumentException('public_key não encontrado na resposta da API.');
        $refreshToken = $data['refresh_token'] ?? throw new InvalidArgumentException('refresh_token não encontrado na resposta da API.');
        $userId = $data['user_id'] ?? throw new InvalidArgumentException('user_id não encontrado na resposta da API.');

        if (! is_string($accessToken) || $accessToken === '') {
            throw new InvalidArgumentException('access_token inválido.');
        }

        if (! is_string($publicKey) || $publicKey === '') {
            throw new InvalidArgumentException('public_key inválido.');
        }

        if (! is_string($refreshToken) || $refreshToken === '') {
            throw new InvalidArgumentException('refresh_token inválido.');
        }

        if (! is_int($userId)) {
            throw new InvalidArgumentException('user_id inválido.');
        }

        $this->accessToken = $accessToken;
        $this->publicKey = $publicKey;
        $this->refreshToken = $refreshToken;
        $this->userId = $userId;
    }

    /**
     * @param array<string, mixed>|null $data
     */
    public static function fromArray(?array $data): self
    {
        if ($data === null) {
            throw new InvalidArgumentException('Dados da API não podem ser nulos.');
        }

        return new self($data);
    }
}
