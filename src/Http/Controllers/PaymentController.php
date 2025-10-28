<?php

namespace Us\PaymentModuleManager\Http\Controllers;

use Illuminate\Routing\Controller;
use Us\PaymentModuleManager\Http\Requests\CreatePaymentRequest;
use Us\PaymentModuleManager\Services\PaymentService;
use Us\PaymentModuleManager\Traits\ApiResponseTrait;
use Illuminate\Support\Facades\Log;
use Throwable;

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
     * @param CreatePaymentRequest $request
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
                'Falha ao processar pagamento: ' . $e->getMessage(),
                500 // Internal Server Error
            );
        }
    }
}
