<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 28/10/2025 20:43:21
*/

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
            $notificationUrl = rtrim(config('app.url'), '/').'/api/mercadopago/webhook';

            $request = [
                'transaction_amount' => $amount,
                'description' => $data['description'] ?? 'Pagamento via API',
                'notification_url' => $notificationUrl,
                'payer' => [
                    'email' => $data['payer_email'],
                ],
            ];

            switch ($data['payment_method_id']) {
                case 'credit_card':
                    $request['token'] = $data['token'];
                    $request['installments'] = $data['installments'];
                    $request['issuer_id'] = $data['issuer_id'];
                    $request['payment_method_id'] = 'visa'; // Exemplo, pode ser dinâmico
                    $request['payer'] = array_merge($request['payer'], [
                        'first_name' => $data['payer']['first_name'],
                        'last_name' => $data['payer']['last_name'],
                        'identification' => [
                            'type' => $data['payer']['identification']['type'],
                            'number' => $data['payer']['identification']['number'],
                        ],
                    ]);
                    break;
                case 'boleto':
                    $request['payment_method_id'] = 'bolbradesco'; // Exemplo de ID para boleto
                    $request['payer'] = array_merge($request['payer'], [
                        'first_name' => $data['payer']['first_name'] ?? null,
                        'last_name' => $data['payer']['last_name'] ?? null,
                        'identification' => [
                            'type' => $data['payer']['identification']['type'],
                            'number' => $data['payer']['identification']['number'],
                        ],
                        'address' => [
                            'zip_code' => $data['payer']['address']['zip_code'],
                            'street_name' => $data['payer']['address']['street_name'],
                            'street_number' => $data['payer']['address']['street_number'],
                            'neighborhood' => $data['payer']['address']['neighborhood'],
                            'city' => $data['payer']['address']['city'],
                            'federal_unit' => $data['payer']['address']['federal_unit'],
                        ],
                    ]);
                    break;
                case 'pix':
                default:
                    $request['payment_method_id'] = 'pix';
                    // Payer já está definido acima
                    break;
            }

            $payment = $this->mpClient->createPayment($request);

            // Retorna os dados relevantes da resposta do Mercado Pago
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

    /**
     * Padroniza a resposta da API do Mercado Pago para a aplicação.
     */
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

        if ($payment->payment_method_id === 'pix') {
            $response['external_resource_url'] = $payment->point_of_interaction->transaction_data->qr_code_base64 ?? null;
        } elseif ($payment->payment_method_id === 'boleto') {
            $response['external_resource_url'] = $payment->point_of_interaction->transaction_data->ticket_url ?? null;
        }

        return $response;
    }
}
