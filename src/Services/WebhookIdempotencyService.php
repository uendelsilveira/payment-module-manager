<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created at: 24/11/25
*/

declare(strict_types=1);

namespace UendelSilveira\PaymentModuleManager\Services;

use Illuminate\Database\Eloquent\Collection;
use UendelSilveira\PaymentModuleManager\Logger\PaymentLogger;
use UendelSilveira\PaymentModuleManager\Models\WebhookLog;
use UendelSilveira\PaymentModuleManager\Repositories\WebhookLogRepository;

final class WebhookIdempotencyService
{
    public function __construct(protected WebhookLogRepository $repository) {}

    public function isProcessed(string $gateway, string $eventId): bool
    {
        return $this->repository->isProcessed($gateway, $eventId);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function logWebhook(
        string $gateway,
        string $eventId,
        string $eventType,
        array $payload
    ): void {
        $this->repository->log($gateway, $eventId, $eventType, $payload);

        PaymentLogger::logWebhookReceived($gateway, $eventType, $payload);
    }

    public function markAsProcessed(string $gateway, string $eventId): void
    {
        $this->repository->markAsProcessed($gateway, $eventId);

        PaymentLogger::logWebhookProcessed($gateway, $eventId);
    }

    public function markAsFailed(string $gateway, string $eventId, string $errorMessage): void
    {
        $this->repository->markAsFailed($gateway, $eventId, $errorMessage);

        PaymentLogger::logWebhookError($gateway, $errorMessage, [
            'event_id' => $eventId,
        ]);
    }

    /**
     * @return Collection<int, WebhookLog>
     */
    public function getUnprocessedWebhooks(int $limit = 100): Collection
    {
        return $this->repository->findUnprocessed($limit);
    }
}
