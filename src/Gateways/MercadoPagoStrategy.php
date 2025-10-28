<?php

namespace Us\PaymentModuleManager\Gateways;

use Us\PaymentModuleManager\Contracts\PaymentGatewayInterface;
use Us\PaymentModuleManager\Contracts\MercadoPagoClientInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

class MercadoPagoStrategy implements PaymentGatewayInterface
{
    protected MercadoPagoClientInterface $mpClient;

    public function __construct(MercadoPagoClientInterface $mpClient)
    {
        $this->mpClient = $mpClient;
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
            $notificationUrl = route('mercadopago.webhook', [], true);
            Log::info('Generated Notification URL:', ['url' => $notificationUrl]); // Adicionado para depuração

            $request = [
                "transaction_amount" => $amount,
                "description" => $data['description'] ?? 'Pagamento via API',
                "payment_method_id" => "pix", // Exemplo: assumindo PIX para simplificar
                "payer" => [
                    "email" => $data['payer_email'] ?? 'test_payer@example.com',
                ],
                "notification_url" => $notificationUrl,
            ];

            $payment = $this->mpClient->createPayment($request);

            // Retorna os dados relevantes da resposta do Mercado Pago
            return [
                'id' => $payment->id,
                'status' => $payment->status,
                'transaction_amount' => $payment->transaction_amount,
                'description' => $payment->description,
                'payment_method_id' => $payment->payment_method_id,
                'status_detail' => $payment->status_detail,
                'external_resource_url' => $payment->point_of_interaction->transaction_data->qr_code_base64 ?? null,
                'metadata' => (array) $payment->metadata,
            ];

        } catch (\Exception $e) {
            Log::error('Erro ao processar pagamento com Mercado Pago: ' . $e->getMessage());
            throw new \Exception('Erro ao processar pagamento: ' . $e->getMessage());
        }
    }
}
