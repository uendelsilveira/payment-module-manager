<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 19/11/2025 14:24:12
*/

namespace UendelSilveira\PaymentModuleManager\Repositories;

use Illuminate\Database\Eloquent\Collection;
use UendelSilveira\PaymentModuleManager\Contracts\WebhookLogRepositoryInterface;
use UendelSilveira\PaymentModuleManager\Models\WebhookLog;

class WebhookLogRepository implements WebhookLogRepositoryInterface
{
    public function create(array $data): WebhookLog
    {
        return WebhookLog::create($data);
    }

    public function find(int $id): ?WebhookLog
    {
        return WebhookLog::find($id);
    }

    public function findBy(string $column, $value): ?WebhookLog
    {
        return WebhookLog::where($column, $value)->first();
    }

    public function update(int $id, array $data): bool
    {
        $webhookLog = $this->find($id);

        return $webhookLog?->update($data) ?? false;
    }

    public function delete(int $id): bool
    {
        $webhookLog = $this->find($id);

        return (bool) $webhookLog?->delete();
    }

    /**
     * @return Collection<int, WebhookLog>
     */
    public function getAll(): Collection
    {
        return WebhookLog::all();
    }

    /**
     * @return Collection<int, WebhookLog>
     */
    public function getPendingWebhooks(): Collection
    {
        return WebhookLog::where('status', 'pending')->get();
    }

    public function isProcessed(string $gateway, string $eventId): bool
    {
        return WebhookLog::where('gateway', $gateway)
            ->where('event_id', $eventId)
            ->where('processed', true)
            ->exists();
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function log(string $gateway, string $eventId, string $eventType, array $payload): void
    {
        WebhookLog::updateOrCreate(
            ['gateway' => $gateway, 'event_id' => $eventId],
            [
                'event_type' => $eventType,
                'payload' => $payload,
                'processed' => false,
            ]
        );
    }

    public function markAsProcessed(string $gateway, string $eventId): void
    {
        WebhookLog::where('gateway', $gateway)
            ->where('event_id', $eventId)
            ->update([
                'processed' => true,
                'processed_at' => now(),
            ]);
    }

    public function markAsFailed(string $gateway, string $eventId, string $errorMessage): void
    {
        WebhookLog::where('gateway', $gateway)
            ->where('event_id', $eventId)
            ->update([
                'processed' => false,
                'error_message' => $errorMessage,
            ]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, WebhookLog>
     */
    public function findUnprocessed(int $limit = 100): \Illuminate\Database\Eloquent\Collection
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, WebhookLog> $result */
        $result = WebhookLog::where('processed', false)
            ->whereNull('error_message')
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get();

        return $result;
    }
}
