<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 04/11/2025 16:09:38
*/

namespace UendelSilveira\PaymentModuleManager\Listeners;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use UendelSilveira\PaymentModuleManager\Events\PaymentStatusChanged;
use UendelSilveira\PaymentModuleManager\Support\LogContext;

/**
 * Listener to send notifications when payment status changes
 */
class SendPaymentStatusNotification
{
    /**
     * Handle the event.
     */
    public function handle(PaymentStatusChanged $paymentStatusChanged): void
    {
        $transaction = $paymentStatusChanged->transaction;
        $oldStatus = $paymentStatusChanged->oldStatus;
        $newStatus = $paymentStatusChanged->newStatus;

        $logContext = LogContext::create()
            ->withCorrelationId()
            ->withTransaction($transaction)
            ->with('old_status', $oldStatus)
            ->with('new_status', $newStatus);

        Log::channel('payment')->info('Processing payment status notification', $logContext->toArray());

        // Get notification settings
        $webhookUrl = config('payment.notifications.webhook_url');
        $emailEnabled = config('payment.notifications.email.enabled', false);
        $smsEnabled = config('payment.notifications.sms.enabled', false);

        // Send webhook notification
        if ($webhookUrl) {
            $this->sendWebhookNotification($webhookUrl, $transaction, $oldStatus, $newStatus, $logContext);
        }

        // Send email notification
        if ($emailEnabled) {
            $this->sendEmailNotification($transaction, $logContext);
        }

        // Send SMS notification
        if ($smsEnabled) {
            $this->sendSmsNotification($transaction, $newStatus, $logContext);
        }
    }

    /**
     * Send webhook notification
     */
    private function sendWebhookNotification(string $url, \UendelSilveira\PaymentModuleManager\Models\Transaction $transaction, string $oldStatus, string $newStatus, LogContext $logContext): void
    {
        try {
            $payload = [
                'event' => 'payment.status_changed',
                'transaction_id' => $transaction->id,
                'external_id' => $transaction->external_id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'gateway' => $transaction->gateway,
                'amount' => $transaction->amount,
                'currency' => $transaction->currency ?? 'BRL',
                'timestamp' => now()->toIso8601String(),
            ];

            $response = Http::timeout(10)
                ->post($url, $payload);

            if ($response instanceof PromiseInterface) {
                /** @var Response $response */
                $response = $response->wait();
            }

            if ($response->successful()) {
                Log::channel('payment')->info('Webhook notification sent successfully', $logContext->with('webhook_url', $url)->toArray());
            } else {
                Log::channel('payment')->warning('Webhook notification failed', $logContext->with('status_code', $response->status())->toArray());
            }
        } catch (\Exception $exception) {
            Log::channel('payment')->error('Webhook notification error', $logContext->withError($exception)->toArray());
        }
    }

    /**
     * Send email notification
     */
    private function sendEmailNotification(\UendelSilveira\PaymentModuleManager\Models\Transaction $transaction, LogContext $logContext): void
    {
        try {
            // Get recipient email from transaction metadata or config
            $recipientEmail = $transaction->metadata['payer_email'] ?? config('payment.notifications.email.default_recipient');

            if (! $recipientEmail) {
                Log::channel('payment')->warning('Email notification skipped - no recipient', $logContext->toArray());

                return;
            }

            // In production, use Laravel Mail here
            // Mail::to($recipientEmail)->send(new PaymentStatusChangedMail($transaction, $oldStatus, $newStatus));

            Log::channel('payment')->info('Email notification would be sent', $logContext->with('recipient', $recipientEmail)->toArray());
        } catch (\Exception $exception) {
            Log::channel('payment')->error('Email notification error', $logContext->withError($exception)->toArray());
        }
    }

    /**
     * Send SMS notification
     */
    private function sendSmsNotification(\UendelSilveira\PaymentModuleManager\Models\Transaction $transaction, string $newStatus, LogContext $logContext): void
    {
        try {
            // Get recipient phone from transaction metadata
            $recipientPhone = $transaction->metadata['payer_phone'] ?? null;

            if (! $recipientPhone) {
                Log::channel('payment')->warning('SMS notification skipped - no phone number', $logContext->toArray());

                return;
            }

            $smsProvider = config('payment.notifications.sms.provider');
            $message = config('payment.notifications.sms.template', 'Your payment status: {status}');
            $message = str_replace('{status}', $newStatus, $message);

            // In production, integrate with SMS provider (Twilio, SNS, etc)
            // Example: $this->smsProvider->send($recipientPhone, $message);

            Log::channel('payment')->info('SMS notification would be sent', $logContext->with('phone', $recipientPhone)->toArray());
        } catch (\Exception $exception) {
            Log::channel('payment')->error('SMS notification error', $logContext->withError($exception)->toArray());
        }
    }
}
