<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 19/11/2025 14:24:12
*/

namespace UendelSilveira\PaymentModuleManager\Gateways;

use Illuminate\Support\Facades\Log;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\MercadoPagoConfig;
use UendelSilveira\PaymentModuleManager\Contracts\MercadoPagoPaymentClientInterface;
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
     * Supported payment methods for Mercado Pago.
     *
     * @var array<string>
     */
    private const SUPPORTED_PAYMENT_METHODS = ['pix', 'credit_card', 'boleto'];

    protected MercadoPagoPaymentClientInterface $paymentClient;

    /**
     * @var callable|null
     */
    protected $webhookUrlGenerator = null;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        protected array $config = [],
        ?MercadoPagoPaymentClientInterface $paymentClient = null,
        ?callable $webhookUrlGenerator = null
    ) {
        if ($paymentClient) {
            $this->paymentClient = $paymentClient;
        } else {
            $this->initializeSDK();
        }

        $this->webhookUrlGenerator = $webhookUrlGenerator;
    }

    protected function initializeSDK(): void
    {
        $accessToken = is_string($this->config['access_token'] ?? null) ? $this->config['access_token'] : '';
        MercadoPagoConfig::setAccessToken($accessToken);

        if ($this->config['sandbox'] ?? false) {
            MercadoPagoConfig::setRuntimeEnviroment(MercadoPagoConfig::LOCAL);
        }

        $this->paymentClient = new MercadoPagoPaymentClientAdapter;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function processPayment(array $data): ProcessPaymentResponse
    {
        try {
            $paymentData = $this->buildPaymentData($data);
            Log::channel('payment')->debug('MP: Sending payment request', ['amount' => $paymentData['transaction_amount'], 'method' => $paymentData['payment_method_id']]);
            $payment = $this->paymentClient->create($paymentData);
            Log::channel('payment')->info('MP: Payment created', ['id' => $payment->id, 'status' => $payment->status]);
            $responseArray = $this->formatResponse($payment);

            $transactionId = is_string($responseArray['transaction_id']) ? $responseArray['transaction_id'] : '';
            $status = $responseArray['status'] instanceof PaymentStatus ? $responseArray['status'] : PaymentStatus::UNKNOWN;

            return new ProcessPaymentResponse($transactionId, $status, $responseArray);
        } catch (MPApiException $mpApiException) {
            Log::channel('payment')->error('MP: API error', ['message' => $mpApiException->getMessage(), 'status_code' => $mpApiException->getStatusCode(), 'api_response' => $mpApiException->getApiResponse()]);

            throw new PaymentFailedException('Mercado Pago error: '.$mpApiException->getMessage(), $mpApiException->getStatusCode(), $mpApiException);
        }
    }

    public function getPaymentStatus(string $transactionId): PaymentStatus
    {
        try {
            $payment = $this->paymentClient->get((int) $transactionId);

            return $this->mapStatus($payment->status);
        } catch (MPApiException $mpApiException) {
            Log::channel('payment')->warning('MP: Failed to get payment status', ['transaction_id' => $transactionId, 'error' => $mpApiException->getMessage()]);

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
            Log::channel('payment')->info('MP: Refund processed', ['refund_id' => $refund->id, 'payment_id' => $transactionId, 'amount' => $refund->amount]);

            return new RefundPaymentResponse((string) $refund->id, (string) $refund->payment_id, $this->mapStatus($refund->status), (float) $refund->amount, (array) $refund);
        } catch (MPApiException $mpApiException) {
            if ($mpApiException->getStatusCode() === 404) {
                throw new TransactionNotFoundException('Payment not found for refund in Mercado Pago: '.$transactionId);
            }

            throw new RefundFailedException('Failed to refund payment: '.$mpApiException->getMessage(), $mpApiException->getStatusCode(), $mpApiException);
        }
    }

    public function cancelPayment(string $transactionId): CancelPaymentResponse
    {
        try {
            $payment = $this->paymentClient->cancel((int) $transactionId);
            Log::channel('payment')->info('MP: Payment cancelled', ['payment_id' => $transactionId, 'status' => $payment->status]);

            return new CancelPaymentResponse((string) $payment->id, $this->mapStatus($payment->status), (array) $payment);
        } catch (MPApiException $mpApiException) {
            if ($mpApiException->getStatusCode() === 404) {
                throw new TransactionNotFoundException('Payment not found for cancellation in Mercado Pago: '.$transactionId);
            }

            throw new PaymentFailedException('Failed to cancel payment: '.$mpApiException->getMessage(), $mpApiException->getStatusCode(), $mpApiException);
        }
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws WebhookProcessingException
     *
     * @return array<string, mixed>
     */
    public function handleWebhook(array $payload): array
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $paymentId = $data['id'] ?? null;

        if (! $paymentId) {
            throw new WebhookProcessingException('Invalid webhook data: missing payment ID');
        }

        try {
            $paymentIdInt = is_numeric($paymentId) ? (int) $paymentId : 0;
            $payment = $this->paymentClient->get($paymentIdInt);

            return [
                'transaction_id' => (string) $payment->id,
                'status' => $this->mapStatus($payment->status),
                'payment_method' => $payment->payment_method_id ?? '',
                'amount' => $payment->transaction_amount,
                'metadata' => (array) ($payment->metadata ?? []),
            ];
        } catch (MPApiException $mpApiException) {
            throw new WebhookProcessingException('Failed to process webhook: '.$mpApiException->getMessage(), $mpApiException->getStatusCode(), $mpApiException);
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

        // Validar método de pagamento
        if (! in_array($paymentMethod, self::SUPPORTED_PAYMENT_METHODS, true)) {
            $methodString = is_string($paymentMethod) ? $paymentMethod : 'unknown';

            throw new PaymentFailedException(
                sprintf(
                    'Invalid payment method: %s. Supported methods: %s',
                    $methodString,
                    implode(', ', self::SUPPORTED_PAYMENT_METHODS)
                )
            );
        }

        $baseData = [
            'transaction_amount' => $amount,
            'description' => $data['description'] ?? 'Payment',
            'payment_method_id' => $paymentMethod,
            'payer' => ['email' => $data['payer_email']],
        ];

        return match ($paymentMethod) {
            'pix' => $this->buildPixPayment($baseData, $data),
            'credit_card' => $this->buildCreditCardPayment($baseData, $data),
            'boleto' => $this->buildBoletoPayment($baseData, $data),
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
        $webhookUrl = $data['webhook_url'] ?? null;

        if ($webhookUrl === null && is_callable($this->webhookUrlGenerator)) {
            $gatewayName = $this->config['name'] ?? 'mercadopago';
            $webhookUrl = call_user_func($this->webhookUrlGenerator, $gatewayName);
        } elseif ($webhookUrl === null) {
            $gatewayName = $this->config['name'] ?? 'mercadopago';
            $webhookUrl = route('payment.webhook', ['gateway' => $gatewayName]);
        }

        return array_merge($base, ['notification_url' => $webhookUrl]);
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function buildCreditCardPayment(array $base, array $data): array
    {
        /** @var array<string, mixed> $payer */
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
                'identification' => ['type' => $dataPayerIdentification['type'] ?? 'CPF', 'number' => $dataPayerIdentification['number'] ?? ''],
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
        /** @var array<string, mixed> $payer */
        $payer = is_array($base['payer'] ?? null) ? $base['payer'] : [];
        $dataPayer = is_array($data['payer'] ?? null) ? $data['payer'] : [];
        $dataPayerIdentification = is_array($dataPayer['identification'] ?? null) ? $dataPayer['identification'] : [];
        $dataPayerAddress = is_array($dataPayer['address'] ?? null) ? $dataPayer['address'] : [];

        return array_merge($base, [
            'date_of_expiration' => $data['expiration_date'] ?? now()->addDays(3)->toIso8601String(),
            'payer' => array_merge($payer, [
                'first_name' => $dataPayer['first_name'] ?? '',
                'last_name' => $dataPayer['last_name'] ?? '',
                'identification' => ['type' => $dataPayerIdentification['type'] ?? 'CPF', 'number' => $dataPayerIdentification['number'] ?? ''],
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
     * @return array<string, mixed>
     */
    private function formatResponse(object $payment): array
    {
        $paymentMethodId = is_string($payment->payment_method_id ?? null) ? $payment->payment_method_id : '';
        $response = [
            'transaction_id' => (string) ($payment->id ?? ''),
            'status' => $this->mapStatus($payment->status ?? null),
            'provider' => 'mercadopago',
            'payment_method' => $paymentMethodId,
            'amount' => (float) ($payment->transaction_amount ?? 0.0),
            'created_at' => $payment->date_created ?? '',
        ];

        if ($paymentMethodId === 'pix' && isset($payment->point_of_interaction->transaction_data)) {
            $transactionData = $payment->point_of_interaction->transaction_data;
            $response['pix_qr_code'] = $transactionData->qr_code ?? null;
            $response['pix_qr_code_base64'] = $transactionData->qr_code_base64 ?? null;
        }

        if ($paymentMethodId === 'boleto' && isset($payment->transaction_details->external_resource_url)) {
            $response['boleto_url'] = $payment->transaction_details->external_resource_url;

            if (isset($payment->barcode->content)) {
                $response['boleto_barcode'] = $payment->barcode->content;
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
}
