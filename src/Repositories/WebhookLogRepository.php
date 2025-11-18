<?php

namespace UendelSilveira\PaymentModuleManager\Repositories;

use Illuminate\Database\Eloquent\Collection;
use UendelSilveira\PaymentModuleManager\Contracts\WebhookLogRepositoryInterface;
use UendelSilveira\PaymentModuleManager\Models\WebhookLog;

class WebhookLogRepository implements WebhookLogRepositoryInterface
{
    /**
     * Cria um novo log de webhook.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): WebhookLog
    {
        return WebhookLog::create($data);
    }

    /**
     * Encontra um log de webhook pelo seu ID.
     */
    public function find(int $id): ?WebhookLog
    {
        return WebhookLog::find($id);
    }

    /**
     * Encontra um log de webhook por uma coluna e valor específicos.
     *
     * @param mixed $value
     */
    public function findBy(string $column, $value): ?WebhookLog
    {
        return WebhookLog::where($column, $value)->first();
    }

    /**
     * Atualiza um log de webhook.
     *
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        $webhookLog = $this->find($id);

        if ($webhookLog instanceof WebhookLog) {
            return $webhookLog->update($data);
        }

        return false;
    }

    /**
     * Deleta um log de webhook.
     */
    public function delete(int $id): bool
    {
        $webhookLog = $this->find($id);

        if ($webhookLog instanceof WebhookLog) {
            return (bool) $webhookLog->delete();
        }

        return false;
    }

    /**
     * Retorna todos os logs de webhook.
     *
     * @return Collection<int, WebhookLog>
     */
    public function getAll(): Collection
    {
        return WebhookLog::all();
    }

    /**
     * Retorna logs de webhooks pendentes de processamento.
     *
     * @return Collection<int, WebhookLog>
     */
    public function getPendingWebhooks(): Collection
    {
        // Assumindo que 'status' e 'pending' são campos/valores relevantes para logs pendentes
        return WebhookLog::where('status', 'pending')->get();
    }
}
