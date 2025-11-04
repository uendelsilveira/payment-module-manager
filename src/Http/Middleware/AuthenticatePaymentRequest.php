<?php

namespace UendelSilveira\PaymentModuleManager\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
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
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ?string $guard = null)
    {
        $authStrategy = Config::get('payment.auth.strategy', 'none');

        switch ($authStrategy) {
            case 'api_token':
                return $this->validateApiToken($request, $next);
            
            case 'laravel_auth':
                return $this->validateLaravelAuth($request, $next, $guard);
            
            case 'custom':
                return $this->validateCustom($request, $next);
            
            case 'none':
            default:
                // Sem autenticação - útil para desenvolvimento
                return $next($request);
        }
    }

    /**
     * Valida usando API Token fixo.
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
     */
    protected function validateLaravelAuth(Request $request, Closure $next, ?string $guard = null)
    {
        if (! auth($guard)->check()) {
            throw new PaymentAuthenticationException('Não autenticado.', 401);
        }

        return $next($request);
    }

    /**
     * Valida usando callback customizado.
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

