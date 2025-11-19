<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 19/11/2025 20:40:00
*/

namespace UendelSilveira\PaymentModuleManager\Services;

use Carbon\Carbon;
use UendelSilveira\PaymentModuleManager\Models\Transaction;

class RetryService
{
    /**
     * Check if a transaction is eligible for retry
     */
    public function isEligibleForRetry(Transaction $transaction, int $maxAttempts = 3): bool
    {
        // Cannot retry if already successful
        if (in_array($transaction->status, ['approved', 'refunded', 'cancelled'])) {
            return false;
        }

        // Cannot retry if max attempts reached
        if ($transaction->retries_count >= $maxAttempts) {
            return false;
        }

        // Check if enough time has passed since last attempt
        if ($transaction->last_attempt_at) {
            $nextRetryTime = $this->calculateNextRetryTime($transaction);

            return Carbon::now()->gte($nextRetryTime);
        }

        return true;
    }

    /**
     * Calculate the next retry time using exponential backoff
     */
    public function calculateNextRetryTime(Transaction $transaction): Carbon
    {
        if (! $transaction->last_attempt_at) {
            return Carbon::now();
        }

        // Exponential backoff: 2^retries_count minutes
        // Attempt 1: 2 minutes, Attempt 2: 4 minutes, Attempt 3: 8 minutes
        $delayMinutes = 2 ** $transaction->retries_count;

        // Cap at 60 minutes
        $delayMinutes = min($delayMinutes, 60);

        return Carbon::parse($transaction->last_attempt_at)->addMinutes($delayMinutes);
    }

    /**
     * Get the delay in seconds until next retry
     */
    public function getDelayUntilNextRetry(Transaction $transaction): int
    {
        $nextRetryTime = $this->calculateNextRetryTime($transaction);
        $now = Carbon::now();

        if ($now->gte($nextRetryTime)) {
            return 0;
        }

        return (int) $now->diffInSeconds($nextRetryTime);
    }
}
