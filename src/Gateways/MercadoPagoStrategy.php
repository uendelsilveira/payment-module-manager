<?php

namespace UendelSilveira\PaymentModuleManager\Gateways;

use Illuminate\Support\Facades\Log;
use UendelSilveira\PaymentModuleManager\Contracts\MercadoPagoClientInterface;
use UendelSilveira\PaymentModuleManager\Contracts\PaymentGatewayInterface;

class MercadoPagoStrategy implements PaymentGatewayInterface
{
    protected MercadoPagoClientInterface $mpClient;

    public function __construct(MercadoPagoClientInterface $mpClient)
    {
        $this->mpClient = $mpClient;
    }

    public function charge(float $amount, array $data): array
    {
        Log::info('[MercadoPagoStrategy] Iniciando cobrança.', ['amount' => $amount, 'data' => $data]);

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

            return $this->formatPaymentResponse($payment);
        } catch (\Exception $e) {
            Log::error('[MercadoPagoStrategy] Erro ao processar pagamento com Mercado Pago.', ['exception' => $e->getMessage()]);

            throw new \Exception('Erro ao processar pagamento: '.$e->getMessage());
        }
    }

    public function getPayment(string $externalPaymentId): array
    {
        Log::info('[MercadoPagoStrategy] Buscando pagamento.', ['external_id' => $externalPaymentId]);

        try {
            $payment = $this->mpClient->getPayment($externalPaymentId);

            return $this->formatPaymentResponse($payment);
        } catch (\Exception $e) {
            Log::error('[MercadoPagoStrategy] Erro ao buscar pagamento no Mercado Pago.', ['exception' => $e->getMessage()]);

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
            'payment_method_id' => 'boleto',
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
            'payment_method_id' => 'credit_card', // Valor fixo para cartão de crédito
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
