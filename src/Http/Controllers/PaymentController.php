<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 19/11/2025 14:24:12
*/

namespace UendelSilveira\PaymentModuleManager\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Throwable;
use UendelSilveira\PaymentModuleManager\Http\Requests\CreatePaymentRequest;
use UendelSilveira\PaymentModuleManager\Http\Requests\RefundRequest;
use UendelSilveira\PaymentModuleManager\Http\Resources\TransactionResource; // Import the new TransactionResource
use UendelSilveira\PaymentModuleManager\Models\Transaction;
use UendelSilveira\PaymentModuleManager\Services\PaymentService;
use UendelSilveira\PaymentModuleManager\Traits\ApiResponseTrait;

class PaymentController extends Controller
{
    use ApiResponseTrait;

    public function __construct(protected PaymentService $paymentService) {}

    public function process(CreatePaymentRequest $createPaymentRequest): \Illuminate\Http\JsonResponse
    {
        Log::info(
            '[PaymentController] Requisição para processar pagamento recebida.',
            ['payload' => $createPaymentRequest->validated()]
        );

        try {
            $validated = $createPaymentRequest->validated();
            assert(is_array($validated));
            $validatedData = $validated;

            if (isset($validatedData['method']) && ! isset($validatedData['gateway'])) {
                $validatedData['gateway'] = $validatedData['method'];
            }

            $transaction = $this->paymentService->processPayment($validatedData);

            return $this->successResponse(
                new TransactionResource($transaction), // Use TransactionResource
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

    public function show(Transaction $transaction): \Illuminate\Http\JsonResponse
    {
        Log::info(
            '[PaymentController] Requisição para obter detalhes da transação.',
            ['transaction_id' => $transaction->id]
        );

        try {
            $updatedTransaction = $this->paymentService->getPaymentDetails($transaction);

            return $this->successResponse(
                new TransactionResource($updatedTransaction), // Use TransactionResource
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

    public function refund(
        Transaction $transaction,
        RefundRequest $refundRequest
    ): \Illuminate\Http\JsonResponse {
        Log::info(
            '[PaymentController] Requisição para estornar pagamento.',
            ['transaction_id' => $transaction->id]
        );

        try {
            $validated = $refundRequest->validated();
            assert(is_array($validated));
            $amountValue = $validated['amount'] ?? null;
            $amount = (is_int($amountValue) || is_float($amountValue)) ? (float) $amountValue : null;

            $refundData = $this->paymentService->refundPayment($transaction, $amount);

            return $this->successResponse(
                $refundData,
                'Estorno processado com sucesso.'
            );
        } catch (Throwable $throwable) {
            Log::error(
                '[PaymentController] Erro ao estornar pagamento.',
                ['transaction_id' => $transaction->id, 'exception' => $throwable->getMessage()]
            );

            return $this->errorResponse(
                'Falha ao processar estorno: '.$throwable->getMessage(),
                500
            );
        }
    }

    public function cancel(Transaction $transaction): \Illuminate\Http\JsonResponse
    {
        Log::info(
            '[PaymentController] Requisição para cancelar pagamento.',
            ['transaction_id' => $transaction->id]
        );

        try {
            $cancelData = $this->paymentService->cancelPayment($transaction);

            return $this->successResponse(
                $cancelData,
                'Pagamento cancelado com sucesso.'
            );
        } catch (Throwable $throwable) {
            Log::error(
                '[PaymentController] Erro ao cancelar pagamento.',
                ['transaction_id' => $transaction->id, 'exception' => $throwable->getMessage()]
            );

            return $this->errorResponse(
                'Falha ao cancelar pagamento: '.$throwable->getMessage(),
                500
            );
        }
    }
}
