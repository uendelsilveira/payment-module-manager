<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 04/11/2025 16:09:38
*/

namespace UendelSilveira\PaymentModuleManager\Gateways;

use Illuminate\Support\Facades\Log;
use UendelSilveira\PaymentModuleManager\Contracts\MercadoPagoClientInterface;
use UendelSilveira\PaymentModuleManager\Contracts\PaymentGatewayInterface;
use UendelSilveira\PaymentModuleManager\Support\LogContext;

class MercadoPagoStrategy implements PaymentGatewayInterface
{
    public function __construct(protected MercadoPagoClientInterface $mpClient) {}

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function charge(float $amount, array $data): array
    {
        $startTime = microtime(true);

        $logContext = LogContext::create()
            ->withCorrelationId()
            ->withGateway('mercadopago')
            ->withAmount($amount)
            ->withPaymentMethod($data['payment_method_id'] ?? 'unknown')
            ->withRequestId()
            ->maskSensitiveData();

        Log::channel('gateway')->info('MercadoPago charge initiated', $logContext->toArray());

        try {
            $payload = $this->buildBasePayload($amount, $data);

            $payload = match ($data['payment_method_id']) {
                'credit_card' => array_merge($payload, $this->buildCreditCardPayload($data)),
                'boleto' => array_merge($payload, $this->buildBoletoPayload($data)),
                default => array_merge($payload, $this->buildPixPayload()),
            };

            $payment = $this->mpClient->createPayment($amount, $payload);
            $response = $this->formatPaymentResponse($payment);

            $logContext->withExternalId($response['id'])
                ->with('status', $response['status'])
                ->withDuration($startTime);

            Log::channel('gateway')->info('MercadoPago charge completed', $logContext->toArray());

            return $response;
        } catch (\Exception $exception) {
            $logContext->withError($exception)->withDuration($startTime);

            Log::channel('gateway')->error('MercadoPago charge failed', $logContext->toArray());

            throw new \Exception('Erro ao processar pagamento: '.$exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayment(string $externalPaymentId): array
    {
        $startTime = microtime(true);

        $logContext = LogContext::create()
            ->withCorrelationId()
            ->withGateway('mercadopago')
            ->withExternalId($externalPaymentId)
            ->withRequestId();

        Log::channel('gateway')->info('Fetching MercadoPago payment', $logContext->toArray());

        try {
            $payment = $this->mpClient->getPayment($externalPaymentId);
            $response = $this->formatPaymentResponse($payment);

            $logContext->with('status', $response['status'])
                ->withDuration($startTime);

            Log::channel('gateway')->info('MercadoPago payment fetched', $logContext->toArray());

            return $response;
        } catch (\Exception $exception) {
            $logContext->withError($exception)->withDuration($startTime);

            Log::channel('gateway')->error('Failed to fetch MercadoPago payment', $logContext->toArray());

            throw new \Exception('Erro ao buscar pagamento: '.$exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    /**
     * @return array<int, object>
     */
    public function getPaymentMethods(): array
    {
        $startTime = microtime(true);

        $logContext = LogContext::create()
            ->withCorrelationId()
            ->withGateway('mercadopago')
            ->withRequestId();

        Log::channel('gateway')->info('Fetching MercadoPago payment methods', $logContext->toArray());

        try {
            $paymentMethods = $this->mpClient->getPaymentMethods();

            $logContext->withDuration($startTime);

            Log::channel('gateway')->info('MercadoPago payment methods fetched', $logContext->toArray());

            return $paymentMethods;
        } catch (\Exception $exception) {
            $logContext->withError($exception)->withDuration($startTime);

            Log::channel('gateway')->error('Failed to fetch MercadoPago payment methods', $logContext->toArray());

            throw new \Exception('Erro ao buscar métodos de pagamento: '.$exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function buildBasePayload(float $amount, array $data): array
    {
        $appUrl = config('app.url');
        $notificationUrl = rtrim(is_string($appUrl) ? $appUrl : '', '/').'/api/mercadopago/webhook';

        return [
            'transaction_amount' => $amount,
            'description' => $data['description'] ?? 'Pagamento via API',
            'notification_url' => $notificationUrl,
            'payer' => [
                'email' => $data['payer_email'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPixPayload(): array
    {
        return [
            'payment_method_id' => 'pix',
        ];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function buildBoletoPayload(array $data): array
    {
        return [
            'payment_method_id' => data_get($data, 'payment_method_id'),
            'payer' => array_merge(data_get($data, 'payer', []), [
                'first_name' => data_get($data, 'payer.first_name'),
                'last_name' => data_get($data, 'payer.last_name'),
                'identification' => [
                    'type' => data_get($data, 'payer.identification.type'),
                    'number' => data_get($data, 'payer.identification.number'),
                ],
                'address' => [
                    'zip_code' => data_get($data, 'payer.address.zip_code'),
                    'street_name' => data_get($data, 'payer.address.street_name'),
                    'street_number' => data_get($data, 'payer.address.street_number'),
                    'neighborhood' => data_get($data, 'payer.address.neighborhood'),
                    'city' => data_get($data, 'payer.address.city'),
                    'federal_unit' => data_get($data, 'payer.address.federal_unit'),
                ],
            ]),
        ];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function buildCreditCardPayload(array $data): array
    {
        return [
            'token' => $data['token'],
            'installments' => $data['installments'],
            'issuer_id' => $data['issuer_id'],
            'payment_method_id' => data_get($data, 'payment_method_id'),
            'payer' => array_merge(data_get($data, 'payer', []), [
                'first_name' => data_get($data, 'payer.first_name'),
                'last_name' => data_get($data, 'payer.last_name'),
                'identification' => [
                    'type' => data_get($data, 'payer.identification.type'),
                    'number' => data_get($data, 'payer.identification.number'),
                ],
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function refund(string $externalPaymentId, ?float $amount = null): array
    {
        $startTime = microtime(true);

        $logContext = LogContext::create()
            ->withCorrelationId()
            ->withGateway('mercadopago')
            ->withExternalId($externalPaymentId)
            ->withRequestId();

        if ($amount !== null) {
            $logContext->withAmount($amount);
        }

        Log::channel('gateway')->info('MercadoPago refund initiated', $logContext->toArray());

        try {
            $payload = [];

            if ($amount !== null) {
                $payload['amount'] = $amount;
            }

            $refund = $this->mpClient->refundPayment($externalPaymentId, $amount);
            $response = $this->formatRefundResponse($refund);

            $logContext->with('refund_id', $response['id'])
                ->with('status', $response['status'])
                ->withDuration($startTime);

            Log::channel('gateway')->info('MercadoPago refund completed', $logContext->toArray());

            return $response;
        } catch (\Exception $exception) {
            $logContext->withError($exception)->withDuration($startTime);

            Log::channel('gateway')->error('MercadoPago refund failed', $logContext->toArray());

            throw new \Exception('Erro ao processar estorno: '.$exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function cancel(string $externalPaymentId): array
    {
        $startTime = microtime(true);

        $logContext = LogContext::create()
            ->withCorrelationId()
            ->withGateway('mercadopago')
            ->withExternalId($externalPaymentId)
            ->withRequestId();

        Log::channel('gateway')->info('MercadoPago cancel initiated', $logContext->toArray());

        try {
            $payment = $this->getPayment($externalPaymentId);

            if (! in_array($payment['status'], ['pending', 'in_process'])) {
                throw new \Exception('Pagamento não pode ser cancelado, pois não está pendente.');
            }

            $payment = $this->mpClient->cancelPayment($externalPaymentId);
            $response = $this->formatPaymentResponse($payment);

            $logContext->with('status', $response['status'])
                ->withDuration($startTime);

            Log::channel('gateway')->info('MercadoPago cancel completed', $logContext->toArray());

            return $response;
        } catch (\Exception $exception) {
            $logContext->withError($exception)->withDuration($startTime);

            Log::channel('gateway')->error('MercadoPago cancel failed', $logContext->toArray());

            throw new \Exception('Erro ao cancelar pagamento: '.$exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    /**
     * @param array<string, mixed>|object $payment
     *
     * @return array<string, mixed>
     */
    private function formatPaymentResponse(array|object $payment): array
    {
        // Converter array para object se necessário
        if (is_array($payment)) {
            $payment = (object) $payment;
        }

        /**
         * @var object{id: string|int, status: string, transaction_amount: float, description: string, payment_method_id: string, status_detail: string, metadata: array<string, mixed>|object|null, point_of_interaction?: object{transaction_data?: object{qr_code_base64?: string, ticket_url?: string}}} $payment
         */
        $response = [
            'id' => $payment->id ?? null,
            'status' => $payment->status ?? 'unknown',
            'transaction_amount' => $payment->transaction_amount ?? 0.0,
            'description' => $payment->description ?? '',
            'payment_method_id' => $payment->payment_method_id ?? 'unknown',
            'status_detail' => $payment->status_detail ?? '',
            'metadata' => (array) ($payment->metadata ?? []),
        ];

        if (isset($payment->point_of_interaction->transaction_data)) {
            if ($payment->payment_method_id === 'pix') {
                $response['external_resource_url'] = $payment->point_of_interaction->transaction_data->qr_code_base64 ?? null;
            } elseif ($payment->payment_method_id === 'boleto') {
                $response['external_resource_url'] = $payment->point_of_interaction->transaction_data->ticket_url ?? null;
            }
        }

        return $response;
    }

    /**
     * @param array<string, mixed>|object $refund
     *
     * @return array<string, mixed>
     */
    private function formatRefundResponse(array|object $refund): array
    {
        // Converter array para object se necessário
        if (is_array($refund)) {
            $refund = (object) $refund;
        }

        /** @var object{id: string|int, payment_id: string|int, amount: float, status: string, date_created?: string} $refund */
        return [
            'id' => $refund->id ?? null,
            'payment_id' => $refund->payment_id ?? null,
            'amount' => $refund->amount ?? 0.0,
            'status' => $refund->status ?? 'unknown',
            'date_created' => $refund->date_created ?? null,
        ];
    }
}
