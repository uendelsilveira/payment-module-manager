<?php

namespace UendelSilveira\PaymentModuleManager\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use UendelSilveira\PaymentModuleManager\Models\Transaction;

/**
 * @mixin Transaction
 */
class TransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'correlation_id' => $this->correlation_id,
            'gateway' => $this->gateway,
            'payment_method' => $this->payment_method,
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'description' => $this->description,
            'installments' => $this->installments,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            // Never expose external_id or raw gateway response directly unless necessary and safe
            // 'external_id' => $this->external_id,
        ];
    }
}
