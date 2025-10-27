<?php

namespace Us\PaymentModuleManager\Gateways;

use Us\PaymentModuleManager\Contracts\PaymentGatewayInterface;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\MercadoPagoConfig;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class MercadoPagoStrategy implements PaymentGatewayInterface
{
    protected PaymentClient $client;

    public function __construct()
    {
        $accessToken = Config::get('payment.gateways.mercadopago.access_token');

        if (empty($accessToken)) {
            throw new \InvalidArgumentException('Mercado Pago access token não configurado.');
        }

        MercadoPagoConfig::setAccessToken($accessToken);
        $this->client = new PaymentClient();
    }

    /**
     * Processa uma cobrança usando a API real do Mercado Pago.
     *
     * @param float $amount
     * @param array $data
     * @return array
     * @throws \Exception
     */
    public function charge(float $amount, array $data): array
    {
        try {
            // Os dados para criar o pagamento podem variar bastante dependendo do tipo de pagamento (cartão, pix, boleto).
            // Para este exemplo, vamos simular um pagamento simples ou um PIX, que é mais direto.
            // Em uma implementação completa, 'data' precisaria conter mais informações (ex: payment_method_id, payer, etc.)

            $request = [
                "transaction_amount" => $amount,
                "description" => $data['description'] ?? 'Pagamento via API',
                "payment_method_id" => "pix", // Exemplo: assumindo PIX para simplificar
                "payer" => [
                    "email" => $data['payer_email'] ?? 'test_payer@example.com',
                ],
                // Outros campos podem ser adicionados conforme a necessidade do Mercado Pago
                // "installments" => 1,
                // "notification_url" => "https://your-site.com/webhooks/mercadopago",
            ];

            $payment = $this->client->create($request);

            // Retorna os dados relevantes da resposta do Mercado Pago
            return [
                'id' => $payment->id,
                'status' => $payment->status,
                'transaction_amount' => $payment->transaction_amount,
                'description' => $payment->description,
                'payment_method_id' => $payment->payment_method_id,
                'status_detail' => $payment->status_detail,
                'external_resource_url' => $payment->point_of_interaction->transaction_data->qr_code_base64 ?? null,
                'metadata' => (array) $payment->metadata, // Converte o objeto metadata para array
            ];

        } catch (MPApiException $e) {
            Log::error('Erro na API do Mercado Pago: ' . $e->getMessage(), [
                'status_code' => $e->getApiResponse()->getStatusCode(),
                'content' => $e->getApiResponse()->getContent(),
            ]);
            throw new \Exception('Erro ao processar pagamento com Mercado Pago: ' . $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Erro inesperado no Mercado Pago Strategy: ' . $e->getMessage());
            throw new \Exception('Erro inesperado ao processar pagamento: ' . $e->getMessage());
        }
    }
}
