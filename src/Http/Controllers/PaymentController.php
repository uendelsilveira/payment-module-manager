<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 28/10/2025 20:43:22
*/

namespace UendelSilveira\PaymentModuleManager\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Throwable;
use UendelSilveira\PaymentModuleManager\Http\Requests\CreatePaymentRequest;
use UendelSilveira\PaymentModuleManager\Models\Transaction;
use UendelSilveira\PaymentModuleManager\Services\PaymentService;
use UendelSilveira\PaymentModuleManager\Traits\ApiResponseTrait;

class PaymentController extends Controller
{
    use ApiResponseTrait;

    protected PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Processa um novo pagamento.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function process(CreatePaymentRequest $request)
    {
        Log::info('[PaymentController] Requisição para processar pagamento recebida.', ['payload' => $request->validated()]);

        try {
            $transaction = $this->paymentService->processPayment($request->validated());

            return $this->successResponse(
                $transaction->toArray(),
                'Pagamento processado com sucesso.',
                201 // Created
            );
        } catch (Throwable $e) {
            Log::error('[PaymentController] Erro ao processar pagamento.', ['exception' => $e->getMessage()]);

            // Em produção, retorna uma resposta de erro padronizada.
            return $this->errorResponse(
                'Falha ao processar pagamento: '.$e->getMessage(),
                500 // Internal Server Error
            );
        }
    }

    /**
     * Exibe os detalhes de uma transação específica.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Transaction $transaction)
    {
        Log::info('[PaymentController] Requisição para obter detalhes da transação.', ['transaction_id' => $transaction->id]);

        try {
            $updatedTransaction = $this->paymentService->getPaymentDetails($transaction);

            return $this->successResponse(
                $updatedTransaction->toArray(),
                'Detalhes da transação obtidos com sucesso.'
            );
        } catch (Throwable $e) {
            Log::error('[PaymentController] Erro ao obter detalhes da transação.', ['transaction_id' => $transaction->id, 'exception' => $e->getMessage()]);

            return $this->errorResponse(
                'Falha ao obter detalhes da transação: '.$e->getMessage(),
                500 // Internal Server Error
            );
        }
    }
}
