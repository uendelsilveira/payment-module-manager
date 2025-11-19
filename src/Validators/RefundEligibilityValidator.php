<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 19/11/2025 03:00:00
*/

namespace UendelSilveira\PaymentModuleManager\Validators;

use Carbon\Carbon;
use UendelSilveira\PaymentModuleManager\Exceptions\RefundNotEligibleException;
use UendelSilveira\PaymentModuleManager\Models\Transaction;

class RefundEligibilityValidator
{
    /**
     * Validate if a transaction is eligible for refund.
     *
     * @param string|null $paymentMethod The payment method used (optional, will use transaction metadata if not provided)
     *
     * @throws RefundNotEligibleException
     */
    public function validateEligibility(Transaction $transaction, ?string $paymentMethod = null): void
    {
        // Get gateway-specific refund rules
        $rules = $this->getRefundRules($transaction->gateway);

        // Extract payment method from transaction metadata if not provided
        if (! $paymentMethod) {
            $metadata = (array) $transaction->metadata;
            $paymentMethod = $metadata['payment_method'] ?? 'default';
        }

        // Check if payment method supports refunds
        $supportedMethods = is_array($rules['supported_methods']) ? $rules['supported_methods'] : [];
        $paymentMethodString = is_string($paymentMethod) ? $paymentMethod : 'unknown';

        if (! in_array($paymentMethodString, $supportedMethods, true)) {
            throw new RefundNotEligibleException(
                sprintf("Payment method '%s' is not eligible for refunds on gateway '%s'", $paymentMethodString, $transaction->gateway)
            );
        }

        // Check time window
        $transactionAge = Carbon::parse($transaction->created_at)->diffInDays(now());
        $timeWindowDays = is_int($rules['time_window_days']) ? $rules['time_window_days'] : 90;

        if ($transactionAge > $timeWindowDays) {
            throw new RefundNotEligibleException(
                sprintf('Transaction is too old for refund. Maximum refund window is %d days, transaction age is %d days', $timeWindowDays, $transactionAge)
            );
        }

        // Check settlement requirement (if applicable)
        $requiresSettlement = (bool) ($rules['requires_settlement'] ?? false);

        if ($requiresSettlement) {
            $this->validateSettlementStatus($transaction);
        }

        // Validate transaction status is refundable
        if (! in_array($transaction->status, ['approved', 'partially_refunded'])) {
            throw new RefundNotEligibleException(
                sprintf("Transaction status '%s' is not eligible for refund. Only 'approved' or 'partially_refunded' transactions can be refunded", $transaction->status)
            );
        }
    }

    /**
     * Get refund rules for a specific gateway.
     *
     * @return array<string, mixed>
     */
    protected function getRefundRules(string $gateway): array
    {
        $config = config('payment.refund_rules');

        if (! is_array($config)) {
            $config = [];
        }

        // Get gateway-specific rules or fall back to global rules
        $rules = is_array($config[$gateway] ?? null) ? $config[$gateway] : [];

        if ($rules === []) {
            $rules = is_array($config['global'] ?? null) ? $config['global'] : [];
        }

        // Ensure all required keys exist with default values
        return [
            'time_window_days' => $rules['time_window_days'] ?? 90,
            'supported_methods' => $rules['supported_methods'] ?? ['credit_card', 'debit_card'],
            'requires_settlement' => $rules['requires_settlement'] ?? false,
        ];
    }

    /**
     * Validate settlement status for gateways that require it.
     *
     * @throws RefundNotEligibleException
     */
    protected function validateSettlementStatus(Transaction $transaction): void
    {
        $metadata = (array) $transaction->metadata;
        $isSettled = $metadata['is_settled'] ?? false;

        if (! $isSettled) {
            throw new RefundNotEligibleException(
                'Transaction must be settled before refund can be processed'
            );
        }
    }
}
