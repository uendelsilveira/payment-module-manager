<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 19/11/2025 14:24:12
*/

namespace UendelSilveira\PaymentModuleManager\Gateways;

use Illuminate\Support\Facades\Log;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\MercadoPagoConfig;
use UendelSilveira\PaymentModuleManager\Contracts\PaymentGatewayInterface;
use UendelSilveira\PaymentModuleManager\DTOs\CancelPaymentResponse;
use UendelSilveira\PaymentModuleManager\DTOs\ProcessPaymentResponse;
use UendelSilveira\PaymentModuleManager\DTOs\RefundPaymentResponse;
use UendelSilveira\PaymentModuleManager\Enums\PaymentStatus;
use UendelSilveira\PaymentModuleManager\Exceptions\PaymentFailedException;
use UendelSilveira\PaymentModuleManager\Exceptions\RefundFailedException;
use UendelSilveira\PaymentModuleManager\Exceptions\TransactionNotFoundException;
use UendelSilveira\PaymentModuleManager\Exceptions\WebhookProcessingException;

class MercadoPagoGateway implements PaymentGatewayInterface
{
    /**
     * @var PaymentClient|object
     *
     * @phpstan-var PaymentClient|object{create: callable, get: callable, cancel: callable}
     */
    public $paymentClient;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(protected array $config = [])
    {
        $this->initializeSDK();
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    protected function initializeSDK(): void
    {
        $accessToken = is_string($this->config['access_token'] ?? null) ? $this->config['access_token'] : '';
        MercadoPagoConfig::setAccessToken($accessToken);

        if ($this->config['sandbox'] ?? false) {
            MercadoPagoConfig::setRuntimeEnviroment(MercadoPagoConfig::LOCAL);
        }

        $this->paymentClient = new PaymentClient;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function processPayment(array $data): ProcessPaymentResponse
    {
        try {
            $paymentData = $this->buildPaymentData($data);

            Log::channel('payment')->debug('MP: Sending payment request', [
                'amount' => $paymentData['transaction_amount'],
                'method' => $paymentData['payment_method_id'],
            ]);

            $payment = $this->paymentClient->create($paymentData);

            Log::channel('payment')->info('MP: Payment created', [
                'id' => $payment->id,
                'status' => $payment->status,
            ]);

            $responseArray = $this->formatResponse($payment);

            return new ProcessPaymentResponse(
                transactionId: $responseArray['transaction_id'],
                status: $responseArray['status'],
                details: $responseArray
            );

        } catch (MPApiException $mpApiException) {
            Log::channel('payment')->error('MP: API error', [
                'message' => $mpApiException->getMessage(),
                'status_code' => $mpApiException->getStatusCode(),
                'api_response' => $mpApiException->getApiResponse(),
            ]);

            throw new PaymentFailedException(
                'Mercado Pago error: '.$mpApiException->getMessage(),
                $mpApiException->getStatusCode(),
                $mpApiException
            );
        }
    }

    public function getPaymentStatus(string $transactionId): PaymentStatus
    {
        try {
            $payment = $this->paymentClient->get((int) $transactionId);

            return $this->mapStatus($payment->status);

        } catch (MPApiException $mpApiException) {
            Log::channel('payment')->warning('MP: Failed to get payment status', [
                'transaction_id' => $transactionId,
                'error' => $mpApiException->getMessage(),
            ]);

            if ($mpApiException->getStatusCode() === 404) {
                throw new TransactionNotFoundException('Payment not found in Mercado Pago: '.$transactionId);
            }

            return PaymentStatus::UNKNOWN;
        }
    }

    public function refundPayment(string $transactionId, ?float $amount = null): RefundPaymentResponse
    {
        try {
            $refundClient = new RefundClient;
            $refund = $refundClient->create((int) $transactionId, $amount);

            Log::channel('payment')->info('MP: Refund processed', [
                'refund_id' => $refund->id,
                'payment_id' => $transactionId,
                'amount' => $refund->amount,
            ]);

            return new RefundPaymentResponse(
                refundId: (string) $refund->id,
                transactionId: (string) $refund->payment_id,
                status: $this->mapStatus($refund->status),
                amount: (float) $refund->amount,
                details: (array) $refund
            );

        } catch (MPApiException $mpApiException) {
            if ($mpApiException->getStatusCode() === 404) {
                throw new TransactionNotFoundException('Payment not found for refund in Mercado Pago: '.$transactionId);
            }

            throw new RefundFailedException(
                'Failed to refund payment: '.$mpApiException->getMessage(),
                $mpApiException->getStatusCode(),
                $mpApiException
            );
        }
    }

    public function cancelPayment(string $transactionId): CancelPaymentResponse
    {
        try {
            $payment = $this->paymentClient->cancel((int) $transactionId);

            Log::channel('payment')->info('MP: Payment cancelled', [
                'payment_id' => $transactionId,
                'status' => $payment->status,
            ]);

            return new CancelPaymentResponse(
                transactionId: (string) $payment->id,
                status: $this->mapStatus($payment->status),
                details: (array) $payment
            );

        } catch (MPApiException $mpApiException) {
            if ($mpApiException->getStatusCode() === 404) {
                throw new TransactionNotFoundException('Payment not found for cancellation in Mercado Pago: '.$transactionId);
            }

            throw new PaymentFailedException(
                'Failed to cancel payment: '.$mpApiException->getMessage(),
                $mpApiException->getStatusCode(),
                $mpApiException
            );
        }
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function createWebhook(array $data): array
    {
        return ['success' => true];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function processWebhook(array $data): array
    {
        $this->validateWebhookSignature($data);

        $dataData = is_array($data['data'] ?? null) ? $data['data'] : [];
        $paymentIdData = $dataData['id'] ?? null;
        $paymentId = is_int($paymentIdData) || is_string($paymentIdData) ? (string) $paymentIdData : null;

        if (! $paymentId) {
            throw new WebhookProcessingException('Invalid webhook data: missing payment ID');
        }

        try {
            $paymentIdInt = is_numeric($paymentId) ? (int) $paymentId : 0;
            $payment = $this->paymentClient->get($paymentIdInt);

            $paymentStatus = is_string($payment->status ?? null) ? $payment->status : null;

            return [
                'transaction_id' => (string) $payment->id,
                'status' => $this->mapStatus($paymentStatus),
                'payment_method' => is_string($payment->payment_method_id ?? null) ? $payment->payment_method_id : '',
                'amount' => $payment->transaction_amount,
                'metadata' => $payment->metadata ?? [],
            ];

        } catch (MPApiException $mpApiException) {
            throw new WebhookProcessingException(
                'Failed to process webhook: '.$mpApiException->getMessage(),
                $mpApiException->getStatusCode(),
                $mpApiException
            );
        }
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function buildPaymentData(array $data): array
    {
        $paymentMethod = $data['payment_method_id'] ?? 'pix';

        $amount = is_float($data['amount'] ?? null) || is_int($data['amount'] ?? null) ? (float) $data['amount'] : 0.0;
        $baseData = [
            'transaction_amount' => $amount,
            'description' => $data['description'] ?? 'Payment',
            'payment_method_id' => $paymentMethod,
            'payer' => [
                'email' => $data['payer_email'],
            ],
        ];

        return match ($paymentMethod) {
            'pix' => $this->buildPixPayment($baseData, $data),
            'credit_card' => $this->buildCreditCardPayment($baseData, $data),
            'boleto' => $this->buildBoletoPayment($baseData, $data),
            default => $baseData,
        };
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function buildPixPayment(array $base, array $data): array
    {
        return array_merge($base, [
            'notification_url' => $data['webhook_url'] ?? route('payment.webhook'),
        ]);
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function buildCreditCardPayment(array $base, array $data): array
    {
        $payer = is_array($base['payer'] ?? null) ? $base['payer'] : [];
        $dataPayer = is_array($data['payer'] ?? null) ? $data['payer'] : [];
        $dataPayerIdentification = is_array($dataPayer['identification'] ?? null) ? $dataPayer['identification'] : [];

        return array_merge($base, [
            'token' => $data['token'] ?? '',
            'installments' => $data['installments'] ?? 1,
            'issuer_id' => $data['issuer_id'] ?? null,
            'payer' => array_merge($payer, [
                'first_name' => $dataPayer['first_name'] ?? '',
                'last_name' => $dataPayer['last_name'] ?? '',
                'identification' => [
                    'type' => $dataPayerIdentification['type'] ?? 'CPF',
                    'number' => $dataPayerIdentification['number'] ?? '',
                ],
            ]),
        ]);
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function buildBoletoPayment(array $base, array $data): array
    {
        $payer = is_array($base['payer'] ?? null) ? $base['payer'] : [];
        $dataPayer = is_array($data['payer'] ?? null) ? $data['payer'] : [];
        $dataPayerIdentification = is_array($dataPayer['identification'] ?? null) ? $dataPayer['identification'] : [];
        $dataPayerAddress = is_array($dataPayer['address'] ?? null) ? $dataPayer['address'] : [];

        return array_merge($base, [
            'date_of_expiration' => $data['expiration_date'] ?? now()->addDays(3)->toIso8601String(),
            'payer' => array_merge($payer, [
                'first_name' => $dataPayer['first_name'] ?? '',
                'last_name' => $dataPayer['last_name'] ?? '',
                'identification' => [
                    'type' => $dataPayerIdentification['type'] ?? 'CPF',
                    'number' => $dataPayerIdentification['number'] ?? '',
                ],
                'address' => [
                    'zip_code' => $dataPayerAddress['zip_code'] ?? '',
                    'street_name' => $dataPayerAddress['street_name'] ?? '',
                    'street_number' => $dataPayerAddress['street_number'] ?? '',
                    'neighborhood' => $dataPayerAddress['neighborhood'] ?? '',
                    'city' => $dataPayerAddress['city'] ?? '',
                    'federal_unit' => $dataPayerAddress['federal_unit'] ?? '',
                ],
            ]),
        ]);
    }

    /**
     * @return array{transaction_id: string, status: PaymentStatus, provider: string, payment_method: string, amount: float, created_at: string, pix_qr_code?: string|null, pix_qr_code_base64?: string|null, boleto_url?: string|null, boleto_barcode?: string|null}
     */
    private function formatResponse(object $payment): array
    {
        $paymentStatus = is_string($payment->status ?? null) ? $payment->status : null;
        $paymentMethodId = is_string($payment->payment_method_id ?? null) ? $payment->payment_method_id : '';
        /** @var array{transaction_id: string, status: PaymentStatus, provider: string, payment_method: string, amount: float, created_at: string, pix_qr_code?: string|null, pix_qr_code_base64?: string|null, boleto_url?: string|null, boleto_barcode?: string|null} $response */
        $response = [
            'transaction_id' => (string) ($payment->id ?? ''),
            'status' => $this->mapStatus($paymentStatus),
            'provider' => 'mercadopago',
            'payment_method' => $paymentMethodId,
            'amount' => is_float($payment->transaction_amount ?? null) || is_int($payment->transaction_amount ?? null) ? (float) $payment->transaction_amount : 0.0,
            'created_at' => is_string($payment->date_created ?? null) ? $payment->date_created : '',
        ];

        if ($paymentMethodId === 'pix' && isset($payment->point_of_interaction)) {
            $pointOfInteraction = $payment->point_of_interaction;

            if (is_object($pointOfInteraction) && isset($pointOfInteraction->transaction_data)) {
                $transactionData = $pointOfInteraction->transaction_data;

                if (is_object($transactionData)) {
                    $response['pix_qr_code'] = is_string($transactionData->qr_code ?? null) ? $transactionData->qr_code : null;
                    $response['pix_qr_code_base64'] = is_string($transactionData->qr_code_base64 ?? null) ? $transactionData->qr_code_base64 : null;
                }
            }
        }

        if ($paymentMethodId === 'boleto' && isset($payment->transaction_details)) {
            $transactionDetails = $payment->transaction_details;

            if (is_object($transactionDetails)) {
                $response['boleto_url'] = is_string($transactionDetails->external_resource_url ?? null) ? $transactionDetails->external_resource_url : null;
            }

            if (isset($payment->barcode) && is_object($payment->barcode)) {
                $response['boleto_barcode'] = is_string($payment->barcode->content ?? null) ? $payment->barcode->content : null;
            }
        }

        return $response;
    }

    private function mapStatus(?string $mpStatus): PaymentStatus
    {
        return match ($mpStatus) {
            'pending' => PaymentStatus::PENDING,
            'approved' => PaymentStatus::APPROVED,
            'in_process' => PaymentStatus::PROCESSING,
            'rejected' => PaymentStatus::REJECTED,
            'refunded' => PaymentStatus::REFUNDED,
            'cancelled' => PaymentStatus::CANCELLED,
            'chargeback' => PaymentStatus::CHARGEBACK,
            'failed' => PaymentStatus::FAILED,
            default => PaymentStatus::UNKNOWN,
        };
    }

    /**
     * @param array<string, mixed> $data
     */
    private function validateWebhookSignature(array $data): void
    {
        if (config('app.env') !== 'production') {
            return;
        }

        $signature = request()->header('x-signature');
        $requestId = request()->header('x-request-id');

        if (! is_string($signature) || ! is_string($requestId)) {
            throw new WebhookProcessingException('Missing webhook signature headers');
        }

        preg_match('/ts=(\d+),v1=([a-f0-9]+)/', $signature, $matches);
        $timestamp = isset($matches[1]) && is_string($matches[1]) ? (int) $matches[1] : null;
        $hash = isset($matches[2]) && is_string($matches[2]) ? $matches[2] : null;

        if (! $timestamp || ! $hash) {
            throw new WebhookProcessingException('Invalid signature format');
        }

        if (abs(time() - $timestamp) > 300) {
            throw new WebhookProcessingException('Webhook signature expired');
        }

        $secret = $this->config['webhook_secret'] ?? '';
        $dataData = is_array($data['data'] ?? null) ? $data['data'] : [];
        $paymentId = is_string($dataData['id'] ?? null) || is_int($dataData['id'] ?? null) ? (string) ($dataData['id']) : '';
        $dataString = sprintf('id=%s;request-id=%s;ts=%d;', $paymentId, $requestId, $timestamp);
        $expectedHash = hash_hmac('sha256', $dataString, is_string($secret) ? $secret : '');

        if (! hash_equals($expectedHash, $hash)) {
            throw new WebhookProcessingException('Invalid webhook signature');
        }
    }
}
