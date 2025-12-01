<?php

declare(strict_types=1);

namespace UendelSilveira\PaymentModuleManager\Logger;

use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;

final class PaymentLogger
{
    private static ?Logger $instance = null;

    private function __construct() {}

    public static function getInstance(): Logger
    {
        if (self::$instance === null) {
            self::$instance = new Logger('payment-module');

            $logPath = __DIR__.'/../../storage/logs';

            // Handler para logs gerais (rotativo - 30 dias)
            $handler = new RotatingFileHandler(
                $logPath.'/payment.log',
                30,
                Level::Info
            );
            $handler->setFormatter(new JsonFormatter);
            self::$instance->pushHandler($handler);

            // Handler para erros críticos
            $errorHandler = new StreamHandler(
                $logPath.'/error.log',
                Level::Error
            );
            $errorHandler->setFormatter(new JsonFormatter);
            self::$instance->pushHandler($errorHandler);

            // Handler para webhook logs
            $webhookHandler = new RotatingFileHandler(
                $logPath.'/webhook.log',
                30,
                Level::Info
            );
            $webhookHandler->setFormatter(new JsonFormatter);
            self::$instance->pushHandler($webhookHandler);
        }

        return self::$instance;
    }

    /**
     * @param array<string, mixed> $payment
     */
    public static function logPaymentCreated(array $payment): void
    {
        self::getInstance()->info('Payment created', [
            'event' => 'payment.created',
            'payment_id' => $payment['id'] ?? null,
            'gateway' => $payment['gateway'] ?? null,
            'amount' => $payment['amount'] ?? null,
            'currency' => $payment['currency'] ?? null,
            'status' => $payment['status'] ?? null,
            'payment_method' => $payment['payment_method'] ?? null,
        ]);
    }

    /**
     * @param array<string, mixed> $payment
     */
    public static function logPaymentUpdated(array $payment, string $previousStatus): void
    {
        self::getInstance()->info('Payment status updated', [
            'event' => 'payment.status_updated',
            'payment_id' => $payment['id'] ?? null,
            'previous_status' => $previousStatus,
            'new_status' => $payment['status'] ?? null,
            'gateway' => $payment['gateway'] ?? null,
        ]);
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function logPaymentError(string $message, array $context = []): void
    {
        self::getInstance()->error($message, array_merge($context, [
            'event' => 'payment.error',
        ]));
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function logWebhookReceived(string $gateway, string $eventType, array $payload): void
    {
        $encodedPayload = json_encode($payload);

        if ($encodedPayload === false) {
            $encodedPayload = 'json_encode_failed';
        }

        self::getInstance()->info('Webhook received', [
            'event' => 'webhook.received',
            'gateway' => $gateway,
            'event_type' => $eventType,
            'payload_hash' => hash('sha256', $encodedPayload),
        ]);
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function logWebhookError(string $gateway, string $message, array $context = []): void
    {
        self::getInstance()->error("Webhook error: {$message}", array_merge($context, [
            'event' => 'webhook.error',
            'gateway' => $gateway,
        ]));
    }

    public static function logWebhookProcessed(string $gateway, string $eventId): void
    {
        self::getInstance()->info('Webhook processed successfully', [
            'event' => 'webhook.processed',
            'gateway' => $gateway,
            'event_id' => $eventId,
        ]);
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function logSecurityEvent(string $message, array $context = []): void
    {
        self::getInstance()->warning($message, array_merge($context, [
            'event' => 'security.alert',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        ]));
    }
}
