<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 28/10/2025 20:43:21
*/

namespace UendelSilveira\PaymentModuleManager\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use UendelSilveira\PaymentModuleManager\Contracts\MercadoPagoClientInterface;
use UendelSilveira\PaymentModuleManager\Models\Transaction;
use UendelSilveira\PaymentModuleManager\Traits\ApiResponseTrait;

class MercadoPagoWebhookController extends Controller
{
    use ApiResponseTrait;



    /**
     * Handle the incoming Mercado Pago webhook request.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function handle(Request $request)
    {
        $webhookId = $request->input('id');
        $webhookType = $request->input('type');
        $webhookAction = $request->input('action');
        $paymentId = $request->input('data.id');

        Log::info('[MercadoPagoWebhookController] Webhook recebido.', [
            'id' => $webhookId,
            'type' => $webhookType,
            'action' => $webhookAction,
            'payment_id' => $paymentId,
            'payload' => $request->all(),
        ]);

        // Apenas processamos notificações do tipo 'payment'
        if ($webhookType !== 'payment') {
            Log::warning('[MercadoPagoWebhookController] Tipo de notificação não suportado.', ['type' => $webhookType]);

            return $this->errorResponse('Tipo de notificação não suportado.', 400);
        }

        if (empty($paymentId)) {
            Log::error('[MercadoPagoWebhookController] ID do pagamento não encontrado na notificação.', ['payload' => $request->all()]);

            return $this->errorResponse('ID do pagamento não encontrado na notificação.', 400);
        }

        try {
            // Consultar a API do Mercado Pago para obter o status atual e canônico do pagamento
            $mpClient = app(\UendelSilveira\PaymentModuleManager\Contracts\MercadoPagoClientInterface::class);
            $mpPayment = $mpClient->getPayment($paymentId);

            // Encontrar a transação local pelo external_id
            $transaction = Transaction::where('external_id', $paymentId)->first();

            if (! $transaction) {
                Log::warning('[MercadoPagoWebhookController] Transação local não encontrada para o external_id.', ['external_id' => $paymentId]);

                return $this->errorResponse('Transação local não encontrada.', 404);
            }

            // Mapeamento de status do Mercado Pago para status internos (exemplo)
            $newStatus = match ($mpPayment->status) {
                'approved', 'authorized', 'in_process' => 'approved',
                'pending' => 'pending',
                'rejected', 'cancelled' => 'rejected',
                'refunded' => 'refunded',
                'charged_back' => 'charged_back',
                default => 'unknown',
            };

            // Atualizar o status da transação local
            if ($transaction->status !== $newStatus) {
                $transaction->status = $newStatus;
                $transaction->metadata = (array) $mpPayment; // Opcional: atualizar metadata completa
                $transaction->save();
                Log::info('[MercadoPagoWebhookController] Status da transação atualizado.', [
                    'transaction_id' => $transaction->id,
                    'external_id' => $paymentId,
                    'old_status' => $transaction->getOriginal('status'),
                    'new_status' => $newStatus,
                    'mp_status' => $mpPayment->status,
                    'action' => $webhookAction,
                ]);
            } else {
                Log::info('[MercadoPagoWebhookController] Status da transação já atualizado ou inalterado.', [
                    'transaction_id' => $transaction->id,
                    'external_id' => $paymentId,
                    'current_status' => $newStatus,
                    'mp_status' => $mpPayment->status,
                    'action' => $webhookAction,
                ]);
            }

            return $this->successResponse(null, 'Webhook processado com sucesso.');

        } catch (\Exception $e) {
            Log::error('[MercadoPagoWebhookController] Erro ao processar webhook.', ['exception' => $e->getMessage(), 'payment_id' => $paymentId]);

            return $this->errorResponse('Erro interno ao processar webhook.', 500);
        }
    }
}
