<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 19/11/2025 14:24:12
*/

namespace UendelSilveira\PaymentModuleManager\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Throwable;
use UendelSilveira\PaymentModuleManager\Http\Requests\CreatePaymentRequest;
use UendelSilveira\PaymentModuleManager\Http\Resources\TransactionResource;
use UendelSilveira\PaymentModuleManager\Models\Transaction;
use UendelSilveira\PaymentModuleManager\Services\PaymentService;
use UendelSilveira\PaymentModuleManager\Traits\ApiResponseTrait;

class PaymentController extends Controller
{
    use ApiResponseTrait;

    public function __construct(protected PaymentService $paymentService) {}

    public function process(CreatePaymentRequest $createPaymentRequest): JsonResponse
    {
        Log::info(
            '[PaymentController] Requisição para processar pagamento recebida.',
            ['payload' => $createPaymentRequest->validated()]
        );

        try {
            /** @var array<string, mixed> $validated */
            $validated = $createPaymentRequest->validated();

            if (! is_array($validated)) {
                throw new \InvalidArgumentException('Validation returned non-array data.');
            }
            $transaction = $this->paymentService->processPayment($validated);

            return $this->successResponse(
                new TransactionResource($transaction),
                'Pagamento processado com sucesso.',
                201
            );
        } catch (Throwable $throwable) {
            Log::error(
                '[PaymentController] Erro ao processar pagamento.',
                ['exception' => $throwable->getMessage()]
            );

            return $this->errorResponse(
                'Falha ao processar pagamento: '.$throwable->getMessage(),
                500
            );
        }
    }

    public function show(Transaction $transaction): JsonResponse
    {
        Log::info(
            '[PaymentController] Requisição para obter detalhes da transação.',
            ['transaction_id' => $transaction->id]
        );

        try {
            $updatedTransaction = $this->paymentService->getPaymentDetails($transaction);

            return $this->successResponse(
                new TransactionResource($updatedTransaction),
                'Detalhes da transação obtidos com sucesso.'
            );
        } catch (Throwable $throwable) {
            Log::error(
                '[PaymentController] Erro ao obter detalhes da transação.',
                ['transaction_id' => $transaction->id, 'exception' => $throwable->getMessage()]
            );

            return $this->errorResponse(
                'Falha ao obter detalhes da transação: '.$throwable->getMessage(),
                500
            );
        }
    }

    public function refund(Request $request, Transaction $transaction): JsonResponse
    {
        Log::info(
            '[PaymentController] Requisição para estornar pagamento.',
            ['transaction_id' => $transaction->id]
        );

        try {
            $amount = $request->input('amount');
            $amount = is_numeric($amount) ? (float) $amount : null;

            $refundResponse = $this->paymentService->refundPayment($transaction, $amount);

            return $this->successResponse(
                $refundResponse->details,
                'Pagamento estornado com sucesso.'
            );
        } catch (Throwable $e) {
            Log::error(
                '[PaymentController] Erro ao estornar pagamento.',
                ['transaction_id' => $transaction->id, 'exception' => $e->getMessage()]
            );

            return $this->errorResponse(
                'Falha ao estornar pagamento: '.$e->getMessage(),
                500
            );
        }
    }

    public function cancel(Transaction $transaction): JsonResponse
    {
        Log::info(
            '[PaymentController] Requisição para cancelar pagamento.',
            ['transaction_id' => $transaction->id]
        );

        try {
            $cancelResponse = $this->paymentService->cancelPayment($transaction);

            return $this->successResponse(
                $cancelResponse->details,
                'Pagamento cancelado com sucesso.'
            );
        } catch (Throwable $e) {
            Log::error(
                '[PaymentController] Erro ao cancelar pagamento.',
                ['transaction_id' => $transaction->id, 'exception' => $e->getMessage()]
            );

            return $this->errorResponse(
                'Falha ao cancelar pagamento: '.$e->getMessage(),
                500
            );
        }
    }
}
