<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 04/11/2025 16:09:38
*/

namespace UendelSilveira\PaymentModuleManager\Support;

use Illuminate\Support\Str;
use UendelSilveira\PaymentModuleManager\Models\Transaction;

class LogContext
{
    /** @var array<string, mixed> */
    private array $context = [];

    public static function create(): self
    {
        return (new self())->withCorrelationId();
    }

    /**
     * Add correlation ID to track related operations
     */
    public function withCorrelationId(?string $correlationId = null): self
    {
        $this->context['correlation_id'] = $correlationId ?? Str::uuid()->toString();

        return $this;
    }

    /**
     * Add transaction context
     */
    public function withTransaction(Transaction $transaction): self
    {
        $this->context['transaction'] = [
            'id' => $transaction->id,
            'external_id' => $transaction->external_id,
            'gateway' => $transaction->gateway,
            'status' => $transaction->status,
            'amount' => $transaction->amount,
        ];

        return $this;
    }

    /**
     * Add transaction ID only
     *
     * @param mixed $transactionId
     */
    public function withTransactionId($transactionId): self
    {
        $this->context['transaction_id'] = $transactionId;

        return $this;
    }

    /**
     * Add gateway context
     */
    public function withGateway(string $gateway): self
    {
        $this->context['gateway'] = $gateway;

        return $this;
    }

    /**
     * Add payment method context
     */
    public function withPaymentMethod(string $paymentMethod): self
    {
        $this->context['payment_method'] = $paymentMethod;

        return $this;
    }

    /**
     * Add amount context
     */
    public function withAmount(float $amount): self
    {
        $this->context['amount'] = $amount;

        return $this;
    }

    /**
     * Add user context
     *
     * @param mixed $user
     */
    public function withUser($user): self
    {
        if ($user) {
            $this->context['user'] = [
                'id' => $user->id ?? null,
                'email' => $user->email ?? null,
            ];
        }

        return $this;
    }

    /**
     * Add external ID context
     */
    public function withExternalId(string $externalId): self
    {
        $this->context['external_id'] = $externalId;

        return $this;
    }

    /**
     * Add webhook context
     *
     * @param array<string, mixed> $data
     */
    public function withWebhook(array $data): self
    {
        $this->context['webhook'] = [
            'type' => $data['type'] ?? null,
            'action' => $data['action'] ?? null,
            'data_id' => $data['data']['id'] ?? null,
        ];

        return $this;
    }

    /**
     * Add request ID for tracking HTTP requests
     */
    public function withRequestId(?string $requestId = null): self
    {
        $this->context['request_id'] = $requestId ?? request()->header('X-Request-ID') ?? Str::uuid()->toString();

        return $this;
    }

    /**
     * Add duration in milliseconds
     */
    public function withDuration(float $startTime): self
    {
        $this->context['duration_ms'] = round((microtime(true) - $startTime) * 1000, 2);

        return $this;
    }

    /**
     * Add error context
     */
    public function withError(\Throwable $throwable): self
    {
        $this->context['error'] = [
            'class' => $throwable::class,
            'message' => $throwable->getMessage(),
            'code' => $throwable->getCode(),
            'file' => $throwable->getFile(),
            'line' => $throwable->getLine(),
        ];

        return $this;
    }

    /**
     * Add retry context
     */
    public function withRetry(int $attempt, int $maxAttempts): self
    {
        $this->context['retry'] = [
            'attempt' => $attempt,
            'max_attempts' => $maxAttempts,
        ];

        return $this;
    }

    /**
     * Add custom key-value pair
     *
     * @param mixed $value
     */
    public function with(string $key, $value): self
    {
        $this->context[$key] = $value;

        return $this;
    }

    /**
     * Add multiple custom key-value pairs
     *
     * @param array<string, mixed> $data
     */
    public function withMany(array $data): self
    {
        $this->context = array_merge($this->context, $data);

        return $this;
    }

    /**
     * Mask sensitive information from context
     */
    public function maskSensitiveData(): self
    {
        $sensitiveFields = config('logging.sensitive_fields', [
            'token', 'access_token', 'password', 'card_number',
            'cvv', 'security_code', 'webhook_secret',
        ]);

        $this->context = $this->maskRecursive($this->context, $sensitiveFields);

        return $this;
    }

    /**
     * Get the context array
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->context;
    }

    /**
     * Recursively mask sensitive fields
     *
     * @param array<string, mixed> $data
     * @param array<int, string> $sensitiveFields
     * @return array<string, mixed>
     */
    private function maskRecursive(array $data, array $sensitiveFields): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->maskRecursive($value, $sensitiveFields);
            } elseif (in_array($key, $sensitiveFields)) {
                $data[$key] = '***MASKED***';
            }
        }

        return $data;
    }
}
