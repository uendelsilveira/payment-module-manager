<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 04/11/2025 16:09:38
*/

namespace UendelSilveira\PaymentModuleManager\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use UendelSilveira\PaymentModuleManager\Models\Transaction;
use UendelSilveira\PaymentModuleManager\Support\LogContext;
use UendelSilveira\PaymentModuleManager\Traits\ApiResponseTrait;

/**
 * Middleware to ensure idempotent payment processing
 *
 * Prevents duplicate payment processing by checking idempotency keys
 */
class EnsureIdempotency
{
    use ApiResponseTrait;

    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Only apply to payment processing endpoints
        if (! $request->is('api/payment/process')) {
            return $next($request);
        }

        $idempotencyKey = $request->header('Idempotency-Key') ?? $request->input('idempotency_key');

        // If no idempotency key provided, continue (optional behavior)
        if (! $idempotencyKey) {
            $context = LogContext::create()
                ->withCorrelationId()
                ->withRequestId()
                ->with('path', $request->path());

            Log::channel('payment')->warning('Payment request without idempotency key', $context->toArray());

            return $next($request);
        }

        // Validate idempotency key format
        if (! $this->isValidIdempotencyKey($idempotencyKey)) {
            return $this->errorResponse(
                'Invalid idempotency key format. Must be alphanumeric, 16-100 characters.',
                422
            );
        }

        // Check cache first (faster than DB)
        $cacheKey = 'idempotency:'.$idempotencyKey;
        $cachedResult = Cache::get($cacheKey);

        if ($cachedResult) {
            $context = LogContext::create()
                ->withCorrelationId()
                ->withRequestId()
                ->with('idempotency_key', $idempotencyKey)
                ->with('cached_transaction_id', $cachedResult['transaction_id']);

            Log::channel('payment')->info('Idempotent request detected (cache)', $context->toArray());

            return response()->json($cachedResult['response'], $cachedResult['status_code']);
        }

        // Check database
        /** @var Transaction|null $existingTransaction */
        $existingTransaction = Transaction::where('idempotency_key', $idempotencyKey)->first();

        if ($existingTransaction) {
            $context = LogContext::create()
                ->withCorrelationId()
                ->withRequestId()
                ->withTransaction($existingTransaction)
                ->with('idempotency_key', $idempotencyKey);

            Log::channel('payment')->info('Idempotent request detected (database)', $context->toArray());

            // Build response from existing transaction
            $response = [
                'success' => true,
                'message' => 'Payment already processed (idempotent request)',
                'data' => $existingTransaction->toArray(),
            ];

            $statusCode = $existingTransaction->status === 'failed' ? 400 : 201;

            // Cache the result for 24 hours
            Cache::put($cacheKey, [
                'response' => $response,
                'status_code' => $statusCode,
                'transaction_id' => $existingTransaction->id,
            ], now()->addHours(24));

            return response()->json($response, $statusCode);
        }

        // Store idempotency key in request for later use
        $request->merge(['_idempotency_key' => $idempotencyKey]);

        $response = $next($request);

        // Cache successful responses
        if ($response->isSuccessful() && $response->getStatusCode() === 201) {
            $responseData = json_decode((string) $response->getContent(), true);

            if (isset($responseData['data']['id'])) {
                Cache::put($cacheKey, [
                    'response' => $responseData,
                    'status_code' => 201,
                    'transaction_id' => $responseData['data']['id'],
                ], now()->addHours(24));
            }
        }

        return $response;
    }

    /**
     * Validate idempotency key format
     */
    private function isValidIdempotencyKey(string $key): bool
    {
        // Must be alphanumeric with dashes/underscores, 16-100 characters
        return preg_match('/^[a-zA-Z0-9_-]{16,100}$/', $key) === 1;
    }
}
