<?php

namespace UendelSilveira\PaymentModuleManager\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;
use UendelSilveira\PaymentModuleManager\PaymentGatewayManager;

class WebhookService
{
    public function __construct(
        protected PaymentGatewayManager $paymentGatewayManager,
        protected WebhookIdempotencyService $idempotencyService
    ) {}

    /**
     * @throws Throwable
     *
     * @return array<string, mixed>
     */
    public function handleWebhook(string $gatewayName, Request $request): array
    {
        Log::info('Webhook received', ['gateway' => $gatewayName]);

        $payload = $request->all();

        if (empty($payload)) {
            throw new \InvalidArgumentException('Invalid webhook payload: empty body');
        }

        $gatewayInstance = $this->paymentGatewayManager->gateway($gatewayName);

        $eventId = $this->extractEventId($gatewayName, $payload);
        $eventType = $this->extractEventType($gatewayName, $payload);

        if ($this->idempotencyService->isProcessed($gatewayName, $eventId)) {
            return [
                'message' => 'Webhook already processed',
                'event_id' => $eventId,
            ];
        }

        $this->idempotencyService->logWebhook($gatewayName, $eventId, $eventType, $payload);

        try {
            $result = $gatewayInstance->handleWebhook($payload);
            $this->idempotencyService->markAsProcessed($gatewayName, $eventId);

            return [
                'message' => 'Webhook processed successfully',
                'event_id' => $eventId,
                'result' => $result,
            ];
        } catch (Throwable $e) {
            $this->idempotencyService->markAsFailed($gatewayName, $eventId, $e->getMessage());

            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractEventId(string $gateway, array $payload): string
    {
        $id = match ($gateway) {
            'stripe' => $payload['id'] ?? null,
            'mercadopago' => is_array($payload['data'] ?? null) ? ($payload['data']['id'] ?? null) : ($payload['id'] ?? null),
            default => null,
        };

        if (! is_string($id) && ! is_numeric($id)) {
            return 'unknown-event-id';
        }

        return (string) $id;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractEventType(string $gateway, array $payload): string
    {
        $type = match ($gateway) {
            'stripe' => $payload['type'] ?? null,
            'mercadopago' => $payload['action'] ?? $payload['type'] ?? null,
            default => null,
        };

        if (! is_string($type)) {
            return 'unknown-event-type';
        }

        return $type;
    }
}
