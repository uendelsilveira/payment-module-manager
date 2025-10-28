<?php

namespace Us\PaymentModuleManager\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Us\PaymentModuleManager\Contracts\MercadoPagoClientInterface;
use Us\PaymentModuleManager\Models\Transaction;
use Us\PaymentModuleManager\Traits\ApiResponseTrait;

class MercadoPagoWebhookController extends Controller
{
    use ApiResponseTrait;

    protected MercadoPagoClientInterface $mpClient;

    public function __construct(MercadoPagoClientInterface $mpClient)
    {
        $this->mpClient = $mpClient;
    }

    /**
     * Handle the incoming Mercado Pago webhook request.
     *
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
            // 2. Consultar a API do Mercado Pago para obter o status atual do pagamento
            $mpPayment = $this->mpClient->getPayment($paymentId);

            // 3. Encontrar a transação local pelo external_id
            $transaction = Transaction::where('external_id', $paymentId)->first();

            if (! $transaction) {
                Log::warning('Transação não encontrada para o external_id: '.$paymentId);

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

        } catch (\Exception $e) {
            Log::error('Erro inesperado no Mercado Pago Webhook Controller: '.$e->getMessage());

            return $this->errorResponse('Erro interno ao processar webhook.', 500);
        }
    }
}
