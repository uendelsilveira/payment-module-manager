<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created at: 24/11/25
*/

declare(strict_types=1);

namespace UendelSilveira\PaymentModuleManager\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use UendelSilveira\PaymentModuleManager\Exceptions\TooManyRequestsException;
use UendelSilveira\PaymentModuleManager\Logger\PaymentLogger;

final class RateLimitMiddleware
{
    public function __construct(protected RateLimiter $limiter) {}

    /**
     * @throws TooManyRequestsException
     */
    public function handle(Request $request, Closure $next, int $maxRequests = 100, int $decayMinutes = 1): mixed
    {
        $clientKey = $this->getClientKey($request);

        if ($this->limiter->tooManyAttempts($clientKey, $maxRequests)) {
            $retryAfter = $this->limiter->availableIn($clientKey);

            PaymentLogger::logSecurityEvent('Rate limit exceeded', [
                'client_key' => $clientKey,
                'retry_after' => $retryAfter,
            ]);

            throw new TooManyRequestsException(
                "Rate limit exceeded. Try again in {$retryAfter} seconds."
            );
        }

        $this->limiter->hit($clientKey, $decayMinutes * 60);

        $response = $next($request);

        if ($response instanceof Response) {
            $this->setRateLimitHeaders(
                $response,
                $maxRequests,
                $this->limiter->remaining($clientKey, $maxRequests),
                $this->limiter->availableIn($clientKey)
            );
        }

        return $response;
    }

    private function getClientKey(Request $request): string
    {
        $ip = $request->ip() ?? 'unknown';
        $apiKeyHeader = $request->header('X-API-KEY', '');
        $apiKey = is_array($apiKeyHeader) ? implode(',', $apiKeyHeader) : $apiKeyHeader;

        return md5($ip.$apiKey);
    }

    private function setRateLimitHeaders(Response $response, int $maxRequests, int $remaining, int $retryAfter): void
    {
        $response->headers->set('X-RateLimit-Limit', (string) $maxRequests);
        $response->headers->set('X-RateLimit-Remaining', (string) $remaining);
        $response->headers->set('X-RateLimit-Reset', (string) $retryAfter);
    }
}
