<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 04/11/2025 16:09:38
*/

namespace UendelSilveira\PaymentModuleManager\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware para rate limiting de requisições de pagamento.
 *
 * Protege contra abuso limitando o número de requisições por IP/usuário.
 */
class RateLimitPaymentRequests
{
    protected RateLimiter $limiter;

    public function __construct(RateLimiter $limiter)
    {
        $this->limiter = $limiter;
    }

    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $limitType = 'default')
    {
        if (! Config::get('payment.rate_limiting.enabled', true)) {
            return $next($request);
        }

        $key = $this->resolveRequestSignature($request, $limitType);
        $maxAttempts = $this->getMaxAttempts($limitType);
        $decayMinutes = 1; // 1 minuto

        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            return $this->buildRateLimitResponse($key, $maxAttempts);
        }

        $this->limiter->hit($key, $decayMinutes * 60);

        $response = $next($request);

        return $this->addHeaders(
            $response,
            $maxAttempts,
            $this->calculateRemainingAttempts($key, $maxAttempts)
        );
    }

    /**
     * Resolve a assinatura única da requisição.
     */
    protected function resolveRequestSignature(Request $request, string $limitType): string
    {
        $user = $request->user();

        if ($user) {
            return sprintf(
                'payment_rate_limit:%s:%s:%s',
                $limitType,
                sha1(get_class($user)),
                $user->getAuthIdentifier()
            );
        }

        return sprintf(
            'payment_rate_limit:%s:%s',
            $limitType,
            sha1($request->ip())
        );
    }

    /**
     * Obtém o número máximo de tentativas para o tipo de limite.
     */
    protected function getMaxAttempts(string $limitType): int
    {
        $limits = [
            'payment_process' => Config::get('payment.rate_limiting.payment_process', 10),
            'payment_query' => Config::get('payment.rate_limiting.payment_query', 60),
            'webhook' => Config::get('payment.rate_limiting.webhook', 100),
            'settings' => Config::get('payment.rate_limiting.settings', 20),
        ];

        return $limits[$limitType] ?? 60;
    }

    /**
     * Calcula as tentativas restantes.
     */
    protected function calculateRemainingAttempts(string $key, int $maxAttempts): int
    {
        return $this->limiter->retriesLeft($key, $maxAttempts);
    }

    /**
     * Constrói a resposta de rate limit excedido.
     */
    protected function buildRateLimitResponse(string $key, int $maxAttempts): Response
    {
        $retryAfter = $this->limiter->availableIn($key);

        return response()->json([
            'success' => false,
            'message' => 'Muitas requisições. Por favor, tente novamente mais tarde.',
            'errors' => [
                'rate_limit' => sprintf(
                    'Você excedeu o limite de %d requisições por minuto. Tente novamente em %d segundos.',
                    $maxAttempts,
                    $retryAfter
                ),
            ],
        ], 429)
            ->withHeaders([
                'X-RateLimit-Limit' => $maxAttempts,
                'X-RateLimit-Remaining' => 0,
                'Retry-After' => $retryAfter,
                'X-RateLimit-Reset' => time() + $retryAfter,
            ]);
    }

    /**
     * Adiciona headers de rate limit à resposta.
     */
    protected function addHeaders(Response $response, int $maxAttempts, int $remainingAttempts): Response
    {
        $response->headers->add([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => max(0, $remainingAttempts),
        ]);

        return $response;
    }
}
