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
use UendelSilveira\PaymentModuleManager\Models\Transaction;
use UendelSilveira\PaymentModuleManager\Support\LogContext;
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
        $startTime = microtime(true);
        $webhookId = $request->input('id');
        $webhookType = $request->input('type');
        $webhookAction = $request->input('action');
        $paymentId = $request->input('data.id');

        $context = LogContext::create()
            ->withCorrelationId()
            ->withGateway('mercadopago')
            ->withRequestId()
            ->withWebhook([
                'id' => $webhookId,
                'type' => $webhookType,
                'action' => $webhookAction,
                'data' => ['id' => $paymentId],
            ]);

        Log::channel('webhook')->info('Webhook received', $context->toArray());

        // Apenas processamos notificações do tipo 'payment'
        if ($webhookType !== 'payment') {
            $context->with('reason', 'unsupported_type');
            Log::channel('webhook')->warning('Webhook type not supported', $context->toArray());

            return $this->errorResponse('Tipo de notificação não suportado.', 400);
        }

        if (empty($paymentId)) {
            $context->with('reason', 'missing_payment_id');
            Log::channel('webhook')->error('Payment ID not found in webhook', $context->toArray());

            return $this->errorResponse('ID do pagamento não encontrado na notificação.', 400);
        }

        $context->withExternalId($paymentId);

        try {
            // Consultar a API do Mercado Pago para obter o status atual e canônico do pagamento
            $mpClient = app(\UendelSilveira\PaymentModuleManager\Contracts\MercadoPagoClientInterface::class);
            $mpPayment = $mpClient->getPayment($paymentId);

            // Encontrar a transação local pelo external_id
            $transaction = Transaction::where('external_id', $paymentId)->first();

            if (! $transaction) {
                $context->with('reason', 'transaction_not_found');
                Log::channel('webhook')->warning('Local transaction not found', $context->toArray());

                return $this->errorResponse('Transação local não encontrada.', 404);
            }

            $context->withTransactionId($transaction->id);

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
                $oldStatus = $transaction->status;
                $transaction->status = $newStatus;
                $transaction->metadata = (array) $mpPayment;
                $transaction->save();

                $context->with('old_status', $oldStatus)
                    ->with('new_status', $newStatus)
                    ->with('mp_status', $mpPayment->status)
                    ->withDuration($startTime);

                Log::channel('webhook')->info('Transaction status updated', $context->toArray());
            } else {
                $context->with('status', $newStatus)
                    ->with('mp_status', $mpPayment->status)
                    ->withDuration($startTime);

                Log::channel('webhook')->info('Transaction status unchanged', $context->toArray());
            }

            return $this->successResponse(null, 'Webhook processado com sucesso.');

        } catch (\Exception $e) {
            $context->withError($e)->withDuration($startTime);
            Log::channel('webhook')->error('Webhook processing failed', $context->toArray());

            return $this->errorResponse('Erro interno ao processar webhook.', 500);
        }
    }
}
