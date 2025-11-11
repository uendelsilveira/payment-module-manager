<?php

namespace UendelSilveira\PaymentModuleManager\Gateways;

use Illuminate\Support\Facades\Log;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\MercadoPagoConfig;
use UendelSilveira\PaymentModuleManager\Contracts\PaymentGatewayInterface;
use UendelSilveira\PaymentModuleManager\Enums\PaymentStatus;
use UendelSilveira\PaymentModuleManager\Exceptions\PaymentGatewayException;

class MercadoPagoGateway implements PaymentGatewayInterface
{
    public $paymentClient;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(protected array $config = [])
    {
        $this->initializeSDK();
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    protected function initializeSDK(): void
    {
        MercadoPagoConfig::setAccessToken($this->config['access_token']);

        if ($this->config['sandbox'] ?? false) {
            MercadoPagoConfig::setRuntimeEnviroment(MercadoPagoConfig::LOCAL);
        }

        $this->paymentClient = new PaymentClient;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function processPayment(array $data): array
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

            return $this->formatResponse($payment);

        } catch (MPApiException $mpApiException) {
            Log::channel('payment')->error('MP: API error', [
                'message' => $mpApiException->getMessage(),
                'status_code' => $mpApiException->getStatusCode(),
                'api_response' => $mpApiException->getApiResponse(),
            ]);

            throw new PaymentGatewayException(
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

            return PaymentStatus::UNKNOWN;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function refundPayment(string $transactionId, ?float $amount = null): array
    {
        try {
            $refundData = [];

            if ($amount !== null) {
                $refundData['amount'] = $amount;
            }

            $refundClient = new RefundClient;
            $refund = $refundClient->refund((int) $transactionId, $refundData);

            Log::channel('payment')->info('MP: Refund processed', [
                'refund_id' => $refund->id,
                'payment_id' => $transactionId,
                'amount' => $refund->amount,
            ]);

            return [
                'id' => $refund->id,
                'payment_id' => $refund->payment_id,
                'amount' => $refund->amount,
                'status' => $refund->status,
                'date_created' => $refund->date_created,
            ];

        } catch (MPApiException $mpApiException) {
            throw new PaymentGatewayException(
                'Failed to refund payment: '.$mpApiException->getMessage(),
                $mpApiException->getStatusCode(),
                $mpApiException
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function cancelPayment(string $transactionId): array
    {
        try {
            // Mercado Pago usa o mesmo client para cancelamento
            $payment = $this->paymentClient->cancel((int) $transactionId);

            Log::channel('payment')->info('MP: Payment cancelled', [
                'payment_id' => $transactionId,
                'status' => $payment->status,
            ]);

            return [
                'id' => $payment->id,
                'status' => $payment->status,
                'transaction_amount' => $payment->transaction_amount,
            ];

        } catch (MPApiException $mpApiException) {
            throw new PaymentGatewayException(
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
        // TODO: criação/config de webhook se aplicável
        return [
            'success' => true,
        ];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function processWebhook(array $data): array
    {
        // Validar assinatura do webhook
        $this->validateWebhookSignature($data);

        // Mercado Pago envia o ID no body
        $paymentId = $data['data']['id'] ?? null;

        if (! $paymentId) {
            throw new PaymentGatewayException('Invalid webhook data: missing payment ID');
        }

        // Buscar dados completos do pagamento
        try {
            $payment = $this->paymentClient->get((int) $paymentId);

            return [
                'transaction_id' => (string) $payment->id,
                'status' => $this->mapStatus($payment->status),
                'payment_method' => $payment->payment_method_id,
                'amount' => $payment->transaction_amount,
                'metadata' => $payment->metadata ?? [],
            ];

        } catch (MPApiException $mpApiException) {
            throw new PaymentGatewayException(
                'Failed to process webhook: '.$mpApiException->getMessage(),
                $mpApiException->getStatusCode(),
                $mpApiException
            );
        }
    }

    private function buildPaymentData(array $data): array
    {
        $paymentMethod = $data['payment_method_id'] ?? 'pix';

        $baseData = [
            'transaction_amount' => (float) $data['amount'],
            'description' => $data['description'] ?? 'Payment',
            'payment_method_id' => $paymentMethod,
            'payer' => [
                'email' => $data['payer_email'],
            ],
        ];

        // Adicionar campos específicos por método
        return match ($paymentMethod) {
            'pix' => $this->buildPixPayment($baseData, $data),
            'credit_card' => $this->buildCreditCardPayment($baseData, $data),
            'boleto' => $this->buildBoletoPayment($baseData, $data),
            default => $baseData,
        };
    }

    private function buildPixPayment(array $base, array $data): array
    {
        return array_merge($base, [
            'notification_url' => $data['webhook_url'] ?? route('payment.webhook'),
        ]);
    }

    private function buildCreditCardPayment(array $base, array $data): array
    {
        return array_merge($base, [
            'token' => $data['token'],
            'installments' => $data['installments'] ?? 1,
            'issuer_id' => $data['issuer_id'] ?? null,
            'payer' => array_merge($base['payer'], [
                'first_name' => $data['payer']['first_name'] ?? '',
                'last_name' => $data['payer']['last_name'] ?? '',
                'identification' => [
                    'type' => $data['payer']['identification']['type'] ?? 'CPF',
                    'number' => $data['payer']['identification']['number'] ?? '',
                ],
            ]),
        ]);
    }

    private function buildBoletoPayment(array $base, array $data): array
    {
        return array_merge($base, [
            'date_of_expiration' => $data['expiration_date'] ?? now()->addDays(3)->toIso8601String(),
            'payer' => array_merge($base['payer'], [
                'first_name' => $data['payer']['first_name'] ?? '',
                'last_name' => $data['payer']['last_name'] ?? '',
                'identification' => [
                    'type' => $data['payer']['identification']['type'] ?? 'CPF',
                    'number' => $data['payer']['identification']['number'] ?? '',
                ],
                'address' => [
                    'zip_code' => $data['payer']['address']['zip_code'] ?? '',
                    'street_name' => $data['payer']['address']['street_name'] ?? '',
                    'street_number' => $data['payer']['address']['street_number'] ?? '',
                    'neighborhood' => $data['payer']['address']['neighborhood'] ?? '',
                    'city' => $data['payer']['address']['city'] ?? '',
                    'federal_unit' => $data['payer']['address']['federal_unit'] ?? '',
                ],
            ]),
        ]);
    }

    private function formatResponse($payment): array
    {
        $response = [
            'transaction_id' => (string) $payment->id,
            'status' => $this->mapStatus($payment->status),
            'provider' => 'mercadopago',
            'payment_method' => $payment->payment_method_id,
            'amount' => $payment->transaction_amount,
            'created_at' => $payment->date_created,
        ];

        // Adicionar dados específicos por método
        if ($payment->payment_method_id === 'pix' && isset($payment->point_of_interaction)) {
            $response['pix_qr_code'] = $payment->point_of_interaction->transaction_data->qr_code ?? null;
            $response['pix_qr_code_base64'] = $payment->point_of_interaction->transaction_data->qr_code_base64 ?? null;
        }

        if ($payment->payment_method_id === 'boleto' && isset($payment->transaction_details)) {
            $response['boleto_url'] = $payment->transaction_details->external_resource_url ?? null;
            $response['boleto_barcode'] = $payment->barcode->content ?? null;
        }

        return $response;
    }

    private function mapStatus(string $mpStatus): PaymentStatus
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

    private function validateWebhookSignature(array $data): void
    {
        // Em produção, validar sempre
        if (config('app.env') !== 'production') {
            return;
        }

        $signature = request()->header('x-signature');
        $requestId = request()->header('x-request-id');

        if (! $signature || ! $requestId) {
            throw new PaymentGatewayException('Missing webhook signature headers');
        }

        // Extrair timestamp e hash
        preg_match('/ts=(\d+),v1=([a-f0-9]+)/', $signature, $matches);
        $timestamp = $matches[1] ?? null;
        $hash = $matches[2] ?? null;

        if (! $timestamp || ! $hash) {
            throw new PaymentGatewayException('Invalid signature format');
        }

        // Verificar replay attack (max 5 minutos)
        if (abs(time() - $timestamp) > 300) {
            throw new PaymentGatewayException('Webhook signature expired');
        }

        // Calcular hash esperado
        $secret = $this->config['webhook_secret'];
        $dataString = sprintf('id=%s;request-id=%s;ts=%s;', $data['data']['id'], $requestId, $timestamp);
        $expectedHash = hash_hmac('sha256', $dataString, (string) $secret);

        if (! hash_equals($expectedHash, $hash)) {
            throw new PaymentGatewayException('Invalid webhook signature');
        }
    }
}
