<?php

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
                $gatewayResponse = $gatewayStrategy->charge($data['amount'], $data);

                $transaction->external_id = $gatewayResponse['id'];
                $transaction->status = $gatewayResponse['status'];
                // FIX: Mescla os dados da requisição original com a resposta para garantir que os dados para reprocessamento sejam mantidos.
                $transaction->metadata = array_merge($data, $gatewayResponse);
                $transaction->save();
            } catch (Throwable $e) {
                $transaction->status = 'failed';
                // Salva os dados da requisição mesmo em caso de falha para permitir o reprocessamento.
                $transaction->metadata = $data;
                $transaction->save();
                Log::error('[PaymentService] Falha ao processar pagamento com gateway.', ['exception' => $e->getMessage()]);
                throw $e;
            }

            Log::info('[PaymentService] Pagamento processado com sucesso.', ['transaction_id' => $transaction->id]);
            return $transaction;
        });
    }

    public function getPaymentDetails(Transaction $transaction): Transaction
    {
        Log::info('[PaymentService] Buscando detalhes da transação.', ['transaction_id' => $transaction->id]);
        if (empty($transaction->external_id)) {
            Log::warning('[PaymentService] Transação não possui ID externo para consulta.', ['transaction_id' => $transaction->id]);
            return $transaction;
        }

        $gatewayStrategy = $this->gatewayManager->create($transaction->gateway);
        $gatewayResponse = $gatewayStrategy->getPayment($transaction->external_id);

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

    public function reprocess(Transaction $transaction): Transaction
    {
        Log::info('[PaymentService] Reprocessando transação.', ['transaction_id' => $transaction->id]);
        $gatewayStrategy = $this->gatewayManager->create($transaction->gateway);

        // Garante que os dados para a cobrança sejam um array.
        $chargeData = (array) $transaction->metadata;

        try {
            $gatewayResponse = $gatewayStrategy->charge($transaction->amount, $chargeData);

            // Sucesso: atualiza os dados e salva.
            $transaction->status = $gatewayResponse['status'];
            $transaction->external_id = $gatewayResponse['id'];
            // Mescla os dados da requisição com a nova resposta para manter a consistência.
            $transaction->metadata = array_merge($chargeData, $gatewayResponse);
            $transaction->retries_count++;
            $transaction->last_attempt_at = now();
            $transaction->save();

        } catch (Throwable $e) {
            // Falha: apenas atualiza os contadores e salva.
            $transaction->retries_count++;
            $transaction->last_attempt_at = now();
            $transaction->save();

            Log::error('[PaymentService] Falha ao reprocessar pagamento.', ['transaction_id' => $transaction->id, 'exception' => $e->getMessage()]);

            // Relança a exceção original para que o comando saiba da falha.
            throw $e;
        }

        return $transaction;
    }
}
