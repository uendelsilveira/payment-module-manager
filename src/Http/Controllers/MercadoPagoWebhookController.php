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
use UendelSilveira\PaymentModuleManager\Jobs\ProcessWebhookJob;
use UendelSilveira\PaymentModuleManager\Models\Transaction;
use UendelSilveira\PaymentModuleManager\Support\LogContext;
use UendelSilveira\PaymentModuleManager\Traits\ApiResponseTrait;

class MercadoPagoWebhookController extends Controller
{
    use ApiResponseTrait;

    /**
     * Handle the incoming Mercado Pago webhook request.
     */
    public function handle(Request $request): \Illuminate\Http\JsonResponse
    {
        $startTime = microtime(true);
        $webhookId = $request->input('id');
        $webhookType = $request->input('type');
        $webhookAction = $request->input('action');
        $paymentId = $request->input('data.id');

        $logContext = LogContext::create()
            ->withCorrelationId()
            ->withGateway('mercadopago')
            ->withRequestId()
            ->withWebhook([
                'id' => $webhookId,
                'type' => $webhookType,
                'action' => $webhookAction,
                'data' => ['id' => $paymentId],
            ]);

        Log::channel('webhook')->info('Webhook received', $logContext->toArray());

        // Check if async processing is enabled
        $asyncEnabled = config('payment.webhook.async_processing', true);

        // Apenas processamos notificações do tipo 'payment'
        if ($webhookType !== 'payment') {
            $logContext->with('reason', 'unsupported_type');
            Log::channel('webhook')->warning('Webhook type not supported', $logContext->toArray());

            return $this->errorResponse('Tipo de notificação não suportado.', 400);
        }

        if (empty($paymentId)) {
            $logContext->with('reason', 'missing_payment_id');
            Log::channel('webhook')->error('Payment ID not found in webhook', $logContext->toArray());

            return $this->errorResponse('ID do pagamento não encontrado na notificação.', 400);
        }

        $logContext->withExternalId($paymentId);

        // Dispatch to queue if async is enabled
        if ($asyncEnabled) {
            ProcessWebhookJob::dispatch(
                'mercadopago',
                $request->all()
            )->onQueue(config('payment.webhook.queue_name', 'webhooks'));

            Log::channel('webhook')->info('Webhook dispatched to queue for async processing', $logContext->toArray());

            return $this->successResponse(
                ['queued' => true],
                'Webhook recebido e enfileirado para processamento',
                202
            );
        }

        try {
            // Consultar a API do Mercado Pago para obter o status atual e canônico do pagamento
            $mpClient = app(\UendelSilveira\PaymentModuleManager\Contracts\MercadoPagoClientInterface::class);
            $mpPayment = $mpClient->getPayment($paymentId);

            // Encontrar a transação local pelo external_id
            $transaction = Transaction::where('external_id', $paymentId)->first();

            if (! $transaction) {
                $logContext->with('reason', 'transaction_not_found');
                Log::channel('webhook')->warning('Local transaction not found', $logContext->toArray());

                return $this->errorResponse('Transação local não encontrada.', 404);
            }

            $logContext->withTransactionId($transaction->id);

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

                $logContext->with('old_status', $oldStatus)
                    ->with('new_status', $newStatus)
                    ->with('mp_status', $mpPayment->status)
                    ->withDuration($startTime);

                Log::channel('webhook')->info('Transaction status updated', $logContext->toArray());
            } else {
                $logContext->with('status', $newStatus)
                    ->with('mp_status', $mpPayment->status)
                    ->withDuration($startTime);

                Log::channel('webhook')->info('Transaction status unchanged', $logContext->toArray());
            }

            return $this->successResponse(null, 'Webhook processado com sucesso.');

        } catch (\Exception $exception) {
            $logContext->withError($exception)->withDuration($startTime);
            Log::channel('webhook')->error('Webhook processing failed', $logContext->toArray());

            return $this->errorResponse('Erro interno ao processar webhook.', 500);
        }
    }
}
