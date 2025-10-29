<?php

namespace UendelSilveira\PaymentModuleManager\Services;

use UendelSilveira\PaymentModuleManager\Models\Transaction;

class ReportService
{
    public function getTransactionSummary(?string $startDate, ?string $endDate): array
    {
        $query = Transaction::query();

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $totalTransactions = $query->count();
        $totalAmount = (float) $query->sum('amount');
        $successfulTransactions = $query->where('status', 'approved')->count();
        $failedTransactions = $totalTransactions - $successfulTransactions;

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

        $transactionsByMethod = $query->selectRaw('payment_method_id, count(*) as total_transactions, sum(amount) as total_amount')
            ->groupBy('payment_method_id')
            ->get()
            ->toArray();

        return $transactionsByMethod;
    }
}
