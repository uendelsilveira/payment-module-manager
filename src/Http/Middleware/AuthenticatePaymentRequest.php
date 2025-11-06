<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 04/11/2025 16:09:38
*/

namespace UendelSilveira\PaymentModuleManager\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use UendelSilveira\PaymentModuleManager\Exceptions\InvalidConfigurationException;
use UendelSilveira\PaymentModuleManager\Exceptions\PaymentAuthenticationException;

/**
 * Middleware para autenticação de requisições de pagamento.
 *
 * Este middleware pode ser configurado para usar diferentes estratégias:
 * - API Token: Valida um token fixo configurado
 * - Laravel Auth: Usa o sistema de autenticação do Laravel (Sanctum, Passport, etc.)
 * - Custom: Permite implementação customizada via callback
 */
class AuthenticatePaymentRequest
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ?string $guard = null)
    {
        $authStrategy = Config::get('payment.auth.strategy', 'none');

        return match ($authStrategy) {
            'api_token' => $this->validateApiToken($request, $next),
            'laravel_auth' => $this->validateLaravelAuth($request, $next, $guard),
            'custom' => $this->validateCustom($request, $next),
            // Sem autenticação - útil para desenvolvimento
            default => $next($request),
        };
    }

    /**
     * Valida usando API Token fixo.
     *
     * @return mixed
     */
    protected function validateApiToken(Request $request, Closure $next)
    {
        $expectedToken = Config::get('payment.auth.api_token');
        $providedToken = $request->bearerToken() ?? $request->header('X-API-Token');

        if (empty($expectedToken)) {
            throw new InvalidConfigurationException('API Token não configurado no sistema.');
        }

        if ($providedToken !== $expectedToken) {
            throw new PaymentAuthenticationException('Token de autenticação inválido.', 401);
        }

        return $next($request);
    }

    /**
     * Valida usando sistema de autenticação do Laravel.
     *
     * @return mixed
     */
    protected function validateLaravelAuth(Request $request, Closure $next, ?string $guard = null)
    {
        /** @var Guard $authGuard */
        $authGuard = Auth::guard($guard);

        if (! $authGuard->check()) {
            throw new PaymentAuthenticationException('Não autenticado.', 401);
        }

        return $next($request);
    }

    /**
     * Valida usando callback customizado.
     *
     * @return mixed
     */
    protected function validateCustom(Request $request, Closure $next)
    {
        $callback = Config::get('payment.auth.custom_callback');

        if (! is_callable($callback)) {
            throw new InvalidConfigurationException('Callback de autenticação customizado não configurado corretamente.');
        }

        $result = call_user_func($callback, $request);

        if ($result !== true) {
            throw new PaymentAuthenticationException('Autenticação falhou.', 401);
        }

        return $next($request);
    }
}
