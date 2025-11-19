<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 19/11/2025 14:24:12
*/

namespace UendelSilveira\PaymentModuleManager\Contracts;

use Illuminate\Database\Eloquent\Collection;
use UendelSilveira\PaymentModuleManager\Models\PaymentGateway;

interface GatewayRepositoryInterface
{
    /**
     * Cria uma nova configuração de gateway.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): PaymentGateway;

    /**
     * Encontra uma configuração de gateway pelo seu ID.
     */
    public function find(int $id): ?PaymentGateway;

    /**
     * Encontra uma configuração de gateway por uma coluna e valor específicos.
     *
     * @param mixed $value
     */
    public function findBy(string $column, $value): ?PaymentGateway;

    /**
     * Atualiza uma configuração de gateway.
     *
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): bool;

    /**
     * Deleta uma configuração de gateway.
     */
    public function delete(int $id): bool;

    /**
     * Retorna todas as configurações de gateway.
     *
     * @return Collection<int, PaymentGateway>
     */
    public function getAll(): Collection;

    /**
     * Retorna apenas as configurações de gateway ativas.
     *
     * @return Collection<int, PaymentGateway>
     */
    public function getActiveGateways(): Collection;
}
