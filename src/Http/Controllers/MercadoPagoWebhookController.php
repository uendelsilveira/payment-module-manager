<?php

namespace Us\PaymentModuleManager\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Us\PaymentModuleManager\Models\Transaction;
use Us\PaymentModuleManager\Traits\ApiResponseTrait;
use Illuminate\Support\Facades\Log;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\MercadoPagoConfig;
use Illuminate\Support\Facades\Config;

class MercadoPagoWebhookController extends Controller
{
    use ApiResponseTrait;

    /**
     * Handle the incoming Mercado Pago webhook request.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handle(Request $request)
    {
        Log::info('Mercado Pago Webhook Received', $request->all());

        // Validação básica: verificar se é uma notificação de pagamento
        if ($request->input('type') !== 'payment') {
            return $this->errorResponse('Tipo de notificação não suportado.', 400);
        }

        $paymentId = $request->input('data.id');

        if (empty($paymentId)) {
            return $this->errorResponse('ID do pagamento não encontrado na notificação.', 400);
        }

        try {
            // 1. Buscar o access_token e configurar o SDK do Mercado Pago
            $accessToken = Config::get('payment.gateways.mercadopago.access_token');
            if (empty($accessToken)) {
                throw new \Exception('Mercado Pago access token não configurado para webhook.');
            }
            MercadoPagoConfig::setAccessToken($accessToken);
            $client = new PaymentClient();

            // 2. Consultar a API do Mercado Pago para obter o status atual do pagamento
            $mpPayment = $client->get($paymentId);

            // 3. Encontrar a transação local pelo external_id
            $transaction = Transaction::where('external_id', $paymentId)->first();

            if (!$transaction) {
                Log::warning('Transação não encontrada para o external_id: ' . $paymentId);
                return $this->errorResponse('Transação local não encontrada.', 404);
            }

            // 4. Atualizar o status da transação local
            $transaction->status = $mpPayment->status; // Atualiza com o status real do MP
            $transaction->metadata = (array) $mpPayment; // Opcional: atualizar metadata completa
            $transaction->save();

            Log::info('Transação atualizada via webhook', [
                'transaction_id' => $transaction->id,
                'external_id' => $paymentId,
                'new_status' => $mpPayment->status,
            ]);

            return $this->successResponse(null, 'Webhook processado com sucesso.');

        } catch (MPApiException $e) {
            Log::error('Erro na API do Mercado Pago ao consultar pagamento via webhook: ' . $e->getMessage(), [
                'status_code' => $e->getApiResponse()->getStatusCode(),
                'content' => $e->getApiResponse()->getContent(),
            ]);
            return $this->errorResponse('Erro ao consultar pagamento no Mercado Pago.', 500);
        } catch (\Exception $e) {
            Log::error('Erro inesperado no Mercado Pago Webhook Controller: ' . $e->getMessage());
            return $this->errorResponse('Erro interno ao processar webhook.', 500);
        }
    }
}
