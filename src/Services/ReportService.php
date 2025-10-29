<?php

namespace UendelSilveira\PaymentModuleManager\Services;

use Illuminate\Support\Facades\DB;
use UendelSilveira\PaymentModuleManager\Models\Transaction;

class ReportService
{
    public function getTransactionSummary(?string $startDate, ?string $endDate): array
    {
        $baseQuery = Transaction::query();

        if ($startDate) {
            $baseQuery->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $baseQuery->whereDate('created_at', '<=', $endDate);
        }

        $totalTransactions = $baseQuery->count();
        $totalAmount = (float) $baseQuery->sum('amount');

        $successfulTransactions = (clone $baseQuery)->where('status', 'approved')->count();
        $failedTransactions = (clone $baseQuery)->where('status', 'failed')->count();

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
