<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 19/11/2025 14:24:12
*/

namespace UendelSilveira\PaymentModuleManager\Contracts;

use Illuminate\Database\Eloquent\Collection;
use UendelSilveira\PaymentModuleManager\Models\WebhookLog;

interface WebhookLogRepositoryInterface
{
    /**
     * Cria um novo log de webhook.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): WebhookLog;

    /**
     * Encontra um log de webhook pelo seu ID.
     */
    public function find(int $id): ?WebhookLog;

    /**
     * Encontra um log de webhook por uma coluna e valor específicos.
     *
     * @param mixed $value
     */
    public function findBy(string $column, $value): ?WebhookLog;

    /**
     * Atualiza um log de webhook.
     *
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): bool;

    /**
     * Deleta um log de webhook.
     */
    public function delete(int $id): bool;

    /**
     * Retorna todos os logs de webhook.
     *
     * @return Collection<int, WebhookLog>
     */
    public function getAll(): Collection;

    /**
     * Retorna logs de webhooks pendentes de processamento.
     *
     * @return Collection<int, WebhookLog>
     */
    public function getPendingWebhooks(): Collection;
}
