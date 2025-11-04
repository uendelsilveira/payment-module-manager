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
    protected MercadoPagoClientInterface $mpClient;

    public function __construct(MercadoPagoClientInterface $mpClient)
    {
        $this->mpClient = $mpClient;
    }

    public function charge(float $amount, array $data): array
    {
        $startTime = microtime(true);

        $context = LogContext::create()
            ->withCorrelationId()
            ->withGateway('mercadopago')
            ->withAmount($amount)
            ->withPaymentMethod($data['payment_method_id'] ?? 'unknown')
            ->withRequestId()
            ->maskSensitiveData();

        Log::channel('gateway')->info('MercadoPago charge initiated', $context->toArray());

        try {
            $payload = $this->buildBasePayload($amount, $data);

            switch ($data['payment_method_id']) {
                case 'credit_card':
                    $payload = array_merge($payload, $this->buildCreditCardPayload($data));
                    break;
                case 'boleto':
                    $payload = array_merge($payload, $this->buildBoletoPayload($data));
                    break;
                case 'pix':
                default:
                    $payload = array_merge($payload, $this->buildPixPayload());
                    break;
            }

            $payment = $this->mpClient->createPayment($payload);
            $response = $this->formatPaymentResponse($payment);

            $context->withExternalId($response['id'])
                ->with('status', $response['status'])
                ->withDuration($startTime);

            Log::channel('gateway')->info('MercadoPago charge completed', $context->toArray());

            return $response;
        } catch (\Exception $e) {
            $context->withError($e)->withDuration($startTime);

            Log::channel('gateway')->error('MercadoPago charge failed', $context->toArray());

            throw new \Exception('Erro ao processar pagamento: '.$e->getMessage());
        }
    }

    public function getPayment(string $externalPaymentId): array
    {
        $startTime = microtime(true);

        $context = LogContext::create()
            ->withCorrelationId()
            ->withGateway('mercadopago')
            ->withExternalId($externalPaymentId)
            ->withRequestId();

        Log::channel('gateway')->info('Fetching MercadoPago payment', $context->toArray());

        try {
            $payment = $this->mpClient->getPayment($externalPaymentId);
            $response = $this->formatPaymentResponse($payment);

            $context->with('status', $response['status'])
                ->withDuration($startTime);

            Log::channel('gateway')->info('MercadoPago payment fetched', $context->toArray());

            return $response;
        } catch (\Exception $e) {
            $context->withError($e)->withDuration($startTime);

            Log::channel('gateway')->error('Failed to fetch MercadoPago payment', $context->toArray());

            throw new \Exception('Erro ao buscar pagamento: '.$e->getMessage());
        }
    }

    private function buildBasePayload(float $amount, array $data): array
    {
        return [
            'transaction_amount' => $amount,
            'description' => $data['description'] ?? 'Pagamento via API',
            'notification_url' => rtrim(config('app.url'), '/').'/api/mercadopago/webhook',
            'payer' => [
                'email' => $data['payer_email'],
            ],
        ];
    }

    private function buildPixPayload(): array
    {
        return [
            'payment_method_id' => 'pix',
        ];
    }

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

    private function formatPaymentResponse(object $payment): array
    {
        $response = [
            'id' => $payment->id,
            'status' => $payment->status,
            'transaction_amount' => $payment->transaction_amount,
            'description' => $payment->description,
            'payment_method_id' => $payment->payment_method_id,
            'status_detail' => $payment->status_detail,
            'metadata' => (array) $payment->metadata,
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
}
