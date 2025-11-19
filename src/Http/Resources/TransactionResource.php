<?php

namespace UendelSilveira\PaymentModuleManager\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use UendelSilveira\PaymentModuleManager\Models\Transaction;

class TransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $resource = $this->resource;

        if ($resource instanceof Transaction) {
            $createdAt = $resource->created_at;
            $updatedAt = $resource->updated_at;
            $lastAttemptAt = $resource->last_attempt_at;

            return [
                'id' => $resource->id,
                'correlation_id' => $resource->correlation_id,
                'gateway' => $resource->gateway,
                'external_id' => $resource->external_id,
                'payment_method' => $resource->payment_method,
                'amount' => (float) $resource->amount,
                'currency' => $resource->currency,
                'status' => $resource->status,
                'description' => $resource->description,
                'idempotency_key' => $resource->idempotency_key,
                'metadata' => $resource->metadata,
                'installments' => $resource->installments,
                'retries_count' => $resource->retries_count,
                'last_attempt_at' => $lastAttemptAt instanceof Carbon ? $lastAttemptAt->toDateTimeString() : null,
                'created_at' => $createdAt instanceof Carbon ? $createdAt->toDateTimeString() : null,
                'updated_at' => $updatedAt instanceof Carbon ? $updatedAt->toDateTimeString() : null,
            ];
        }

        $fallback = parent::toArray($request);

        return is_array($fallback) ? $fallback : (array) $fallback;
    }
}
