<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 28/10/2025 20:43:21
*/

namespace UendelSilveira\PaymentModuleManager\Services;

use Carbon\Carbon;
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

    /**
     * Processa um pagamento novo.
     */
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
                $gatewayResponse = $gatewayStrategy->charge($data['amount'], array_merge($data, [
                    'payment_method_id' => $data['payment_method_id'] ?? 'pix',
                ]));

                $transaction->external_id = $gatewayResponse['id'];
                $transaction->status = $gatewayResponse['status'];
                $transaction->metadata = $gatewayResponse;
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

    /**
     * Busca os detalhes de um pagamento no gateway e atualiza a transação local.
     */
    public function getPaymentDetails(Transaction $transaction): Transaction
    {
        Log::info('[PaymentService] Buscando detalhes da transação.', ['transaction_id' => $transaction->id]);

        if (empty($transaction->external_id)) {
            Log::warning('[PaymentService] Transação não possui ID externo para consulta.', ['transaction_id' => $transaction->id]);
            // Retorna a transação como está, pois não pode ser consultada no gateway.
            return $transaction;
        }

        $gatewayStrategy = $this->gatewayManager->create($transaction->gateway);
        $gatewayResponse = $gatewayStrategy->getPayment($transaction->external_id);

        // Compara o status e atualiza se necessário
        if ($gatewayResponse['status'] !== $transaction->status) {
            Log::info('[PaymentService] Status da transação alterado no gateway. Atualizando localmente.', [
                'transaction_id' => $transaction->id,
                'old_status' => $transaction->status,
                'new_status' => $gatewayResponse['status'],
            ]);

            $transaction->status = $gatewayResponse['status'];
            $transaction->metadata = array_merge((array) $transaction->metadata, $gatewayResponse);
            $transaction->save();
        }

        return $transaction;
    }

    /**
     * Retorna as transações falhas que podem ser reprocessadas.
     */
    public function getFailedTransactions()
    {
        return Transaction::where('status', 'failed')
            ->where(function ($query) {
                $query->whereNull('last_attempt_at')
                    ->orWhere('last_attempt_at', '<', Carbon::now()->subMinutes(5));
            })
            ->where('retries_count', '<', 3)
            ->get();
    }

    /**
     * Reprocessa uma transação falha existente.
     */
    public function reprocess(Transaction $transaction): Transaction
    {
        Log::info('[PaymentService] Reprocessando transação.', ['transaction_id' => $transaction->id]);

        $gatewayStrategy = $this->gatewayManager->create($transaction->gateway);

        return DB::transaction(function () use ($transaction, $gatewayStrategy) {
            try {
                $gatewayResponse = $gatewayStrategy->charge($transaction->amount, array_merge((array) $transaction->metadata, [
                    'payment_method_id' => $transaction->metadata['payment_method_id'] ?? 'pix',
                ]));

                $transaction->status = $gatewayResponse['status'];
                $transaction->external_id = $gatewayResponse['id'];
                $transaction->metadata = $gatewayResponse;
                $transaction->retries_count += 1;
                $transaction->last_attempt_at = now();
                $transaction->save();

            } catch (Throwable $e) {
                $transaction->retries_count += 1;
                $transaction->last_attempt_at = now();
                $transaction->save();

                Log::error('[PaymentService] Falha ao reprocessar pagamento.', ['transaction_id' => $transaction->id, 'exception' => $e->getMessage()]);
            }

            return $transaction;
        });
    }
}
