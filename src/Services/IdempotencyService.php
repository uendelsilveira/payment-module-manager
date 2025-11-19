<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 19/11/2025 20:23:00
*/

namespace UendelSilveira\PaymentModuleManager\Services;

use Illuminate\Support\Facades\Cache;

class IdempotencyService
{
    /**
     * Check if a request with the given key has already been processed
     *
     * @return array<string, mixed>|null
     */
    public function check(string $key): ?array
    {
        /** @var array<string, mixed>|null $result */
        $result = Cache::get($this->getCacheKey($key));

        return $result;
    }

    /**
     * Record a processed request with its response data
     *
     * @param array<string, mixed> $data
     */
    public function record(string $key, array $data, int $ttl = 86400): void
    {
        Cache::put($this->getCacheKey($key), $data, $ttl);
    }

    /**
     * Get the cache key for an idempotency key
     */
    protected function getCacheKey(string $key): string
    {
        return 'idempotency:'.$key;
    }
}
