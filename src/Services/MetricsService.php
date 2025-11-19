<?php

namespace UendelSilveira\PaymentModuleManager\Services;

use Illuminate\Support\Facades\DB;
use UendelSilveira\PaymentModuleManager\Enums\PaymentStatus;
use UendelSilveira\PaymentModuleManager\Models\Transaction;

class MetricsService
{
    /**
     * Get transaction metrics.
     *
     * @return array<string, mixed>
     */
    public function getMetrics(): array
    {
        $totalTransactions = Transaction::count();

        $statusCounts = Transaction::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $count = $statusCounts[PaymentStatus::APPROVED->value] ?? 0;
        $successCount = is_numeric($count) ? (int) $count : 0;
        $successRate = $totalTransactions > 0 ? ($successCount / $totalTransactions) * 100 : 0;

        $sum = Transaction::where('status', PaymentStatus::APPROVED->value)->sum('amount');
        $totalVolume = is_numeric($sum) ? (float) $sum : 0.0;

        return [
            'total_transactions' => $totalTransactions,
            'total_volume' => $totalVolume,
            'success_rate' => round($successRate, 2),
            'status_breakdown' => $statusCounts,
            'generated_at' => now()->toIso8601String(),
        ];
    }
}
