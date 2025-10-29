<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 28/10/2025 20:43:21
*/

namespace UendelSilveira\PaymentModuleManager\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use UendelSilveira\PaymentModuleManager\Contracts\TransactionRepositoryInterface;
use UendelSilveira\PaymentModuleManager\Models\Transaction;

class PaymentService
{
    protected GatewayManager $gatewayManager;

    protected TransactionRepositoryInterface $transactionRepository;

    public function __construct(GatewayManager $gatewayManager, TransactionRepositoryInterface $transactionRepository)
    {
        $this->gatewayManager = $gatewayManager;
        $this->transactionRepository = $transactionRepository;
    }

    public function processPayment(array $data): Transaction
    {
        Log::info('[PaymentService] Iniciando processamento de pagamento.', ['data' => $data]);

        $gatewayStrategy = $this->gatewayManager->create($data['method']);

        return DB::transaction(function () use ($data, $gatewayStrategy) {
            $transaction = $this->transactionRepository->create([
                'gateway' => $data['method'],
                'amount' => $data['amount'],
                'description' => $data['description'],
                'status' => 'pending',
            ]);

            try {
                // Passa todos os dados validados para a estratégia
                // Certifica-se de que payment_method_id está presente para a estratégia
                $gatewayResponse = $gatewayStrategy->charge($data['amount'], array_merge($data, [
                    'payment_method_id' => $data['payment_method_id'] ?? 'pix',
                ]));

                $transaction->external_id = $gatewayResponse['id'];
                $transaction->status = $gatewayResponse['status'];
                $transaction->metadata = $gatewayResponse; // Armazena a resposta completa para referência
                $transaction->save();

            } catch (Throwable $e) {
                $transaction->status = 'failed';
                $transaction->save();

                Log::error('[PaymentService] Falha ao processar pagamento com gateway.', ['exception' => $e->getMessage()]);

                throw $e;
            }

            Log::info('[PaymentService] Pagamento processado com sucesso.', ['transaction_id' => $transaction->id]);

            return $transaction;
        });
    }
}
