<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 04/11/2025 16:09:38
*/

namespace UendelSilveira\PaymentModuleManager\Services;

use Illuminate\Support\Facades\DB;
use UendelSilveira\PaymentModuleManager\Models\Transaction;

class ReportService
{
    public function getTransactionSummary(?string $startDate, ?string $endDate): array
    {
        $builder = Transaction::query();

        if ($startDate) {
            $builder->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $builder->whereDate('created_at', '<=', $endDate);
        }

        $totalTransactions = $builder->count();
        $totalAmount = (float) $builder->sum('amount');

        $successfulTransactions = (clone $builder)->where('status', 'approved')->count();
        $failedTransactions = (clone $builder)->where('status', 'failed')->count();

        return [
            'total_transactions' => $totalTransactions,
            'total_amount' => $totalAmount,
            'successful_transactions' => $successfulTransactions,
            'failed_transactions' => $failedTransactions,
        ];
    }

    public function getTransactionsByMethod(?string $startDate, ?string $endDate): array
    {
        $query = Transaction::query();

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        // Usar a sintaxe de acesso a JSON do Laravel para select e group by
        $transactionsByMethod = $query->select(
            'metadata->payment_method_id as payment_method_id',
            DB::raw('count(*) as total_transactions'),
            DB::raw('sum(amount) as total_amount')
        )
            ->groupBy('metadata->payment_method_id')
            ->get()
            ->toArray();

        return $transactionsByMethod;
    }
}
