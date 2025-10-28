<?php

namespace Us\PaymentModuleManager\Gateways;

use Illuminate\Support\Facades\Log;
use Us\PaymentModuleManager\Contracts\MercadoPagoClientInterface;
use Us\PaymentModuleManager\Contracts\PaymentGatewayInterface;

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
     *
     * @throws \Exception
     */
    public function charge(float $amount, array $data): array
    {
        try {
            $notificationUrl = rtrim(config('app.url'), '/').'/api/mercadopago/webhook';
            Log::info('Generated Notification URL:', ['url' => $notificationUrl]);

            $request = [
                'transaction_amount' => $amount,
                'description' => $data['description'] ?? 'Pagamento via API',
                'payment_method_id' => 'pix',
                'payer' => [
                    'email' => $data['payer_email'] ?? 'test_payer@example.com',
                ],
                'notification_url' => $notificationUrl,
            ];

            $payment = $this->mpClient->createPayment($request);

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
            Log::error('Erro ao processar pagamento com Mercado Pago: '.$e->getMessage());

            throw new \Exception('Erro ao processar pagamento: '.$e->getMessage());
        }
    }
}
