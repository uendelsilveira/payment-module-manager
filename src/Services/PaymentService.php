<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 28/10/2025 20:43:22
*/

namespace Us\PaymentModuleManager\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use Us\PaymentModuleManager\Contracts\TransactionRepositoryInterface;
use Us\PaymentModuleManager\Models\Transaction;

class PaymentService
{
    protected GatewayManager $gatewayManager;

    protected TransactionRepositoryInterface $transactionRepository;

    public function __construct(GatewayManager $gatewayManager, TransactionRepositoryInterface $transactionRepository)
    {
        $this->gatewayManager = $gatewayManager;
        $this->transactionRepository = $transactionRepository;
    }

    /**
     * Orquestra o processo completo de pagamento.
     *
     * @param array $data Dados validados da requisição (method, amount, description).
     *
     * @throws Throwable
     */
    public function processPayment(array $data): Transaction
    {
        Log::info('[PaymentService] Iniciando processamento de pagamento.', ['data' => $data]);

        // O GatewayManager seleciona a estratégia correta com base no 'method' escolhido pelo usuário.
        $gatewayStrategy = $this->gatewayManager->create($data['method']);

        // Envolve a lógica em uma transação de banco de dados para garantir a consistência.
        return DB::transaction(function () use ($data, $gatewayStrategy) {
            // 1. Cria um registro inicial da transação no banco de dados.
            $transaction = $this->transactionRepository->create([
                'gateway' => $data['method'],
                'amount' => $data['amount'],
                'description' => $data['description'],
                'status' => 'pending', // Status inicial
            ]);

            try {
                // 2. Executa a cobrança usando a API externa do gateway.
                $gatewayResponse = $gatewayStrategy->charge($data['amount'], [
                    'description' => $data['description'],
                    'payer_email' => $data['payer_email'] ?? null,
                ]);

                // 3. Atualiza a transação com a resposta bem-sucedida do gateway.
                $transaction->external_id = $gatewayResponse['id'];
                $transaction->status = $gatewayResponse['status'];
                $transaction->metadata = $gatewayResponse; // Armazena a resposta completa para referência
                $transaction->save();

            } catch (Throwable $e) {
                // 4. Em caso de falha na comunicação com o gateway, marca a transação como 'failed'.
                $transaction->status = 'failed';
                $transaction->save();

                Log::error('[PaymentService] Falha ao processar pagamento com gateway.', ['exception' => $e->getMessage()]);

                // Relança a exceção para que o controller possa capturá-la e retornar um erro apropriado.
                throw $e;
            }

            Log::info('[PaymentService] Pagamento processado com sucesso.', ['transaction_id' => $transaction->id]);

            return $transaction;
        });
    }
}
