<?php

namespace UendelSilveira\PaymentModuleManager\Repositories;

use Illuminate\Database\Eloquent\Collection;
use UendelSilveira\PaymentModuleManager\Contracts\GatewayRepositoryInterface;
use UendelSilveira\PaymentModuleManager\Models\PaymentGateway;

class GatewayRepository implements GatewayRepositoryInterface
{
    /**
     * Cria uma nova configuração de gateway.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): PaymentGateway
    {
        return PaymentGateway::create($data);
    }

    /**
     * Encontra uma configuração de gateway pelo seu ID.
     */
    public function find(int $id): ?PaymentGateway
    {
        return PaymentGateway::find($id);
    }

    /**
     * Encontra uma configuração de gateway por uma coluna e valor específicos.
     *
     * @param mixed $value
     */
    public function findBy(string $column, $value): ?PaymentGateway
    {
        return PaymentGateway::where($column, $value)->first();
    }

    /**
     * Atualiza uma configuração de gateway.
     *
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        $gateway = $this->find($id);

        if ($gateway instanceof PaymentGateway) {
            return $gateway->update($data);
        }

        return false;
    }

    /**
     * Deleta uma configuração de gateway.
     */
    public function delete(int $id): bool
    {
        $gateway = $this->find($id);

        if ($gateway instanceof PaymentGateway) {
            return (bool) $gateway->delete();
        }

        return false;
    }

    /**
     * Retorna todas as configurações de gateway.
     *
     * @return Collection<int, PaymentGateway>
     */
    public function getAll(): Collection
    {
        return PaymentGateway::all();
    }

    /**
     * Retorna apenas as configurações de gateway ativas.
     *
     * @return Collection<int, PaymentGateway>
     */
    public function getActiveGateways(): Collection
    {
        return PaymentGateway::where('is_active', true)->get();
    }
}
