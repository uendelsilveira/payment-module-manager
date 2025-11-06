<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 04/11/2025 16:09:38
*/

namespace UendelSilveira\PaymentModuleManager\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use UendelSilveira\PaymentModuleManager\Exceptions\InvalidConfigurationException;
use UendelSilveira\PaymentModuleManager\Exceptions\PaymentAuthorizationException;

/**
 * Middleware para autorização de ações de pagamento.
 *
 * Verifica se o usuário autenticado tem permissão para executar a ação solicitada.
 */
class AuthorizePaymentAction
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $permission)
    {
        $authorizationStrategy = Config::get('payment.authorization.strategy', 'none');

        return match ($authorizationStrategy) {
            'callback' => $this->authorizeViaCallback($request, $next, $permission),
            'laravel_gate' => $this->authorizeViaGate($request, $next, $permission),
            // Sem autorização - útil para desenvolvimento
            default => $next($request),
        };
    }

    /**
     * Autoriza usando callback customizado.
     *
     * @return mixed
     */
    protected function authorizeViaCallback(Request $request, Closure $next, string $permission)
    {
        $callback = Config::get('payment.authorization.callback');

        if (! is_callable($callback)) {
            throw new InvalidConfigurationException('Callback de autorização não configurado corretamente.');
        }

        /** @var Authenticatable|null $user */
        $user = Auth::user();
        $result = call_user_func($callback, $user, $permission, $request);

        if ($result !== true) {
            throw new PaymentAuthorizationException('Você não tem permissão para executar esta ação.', 403);
        }

        return $next($request);
    }

    /**
     * Autoriza usando Laravel Gates.
     *
     * @return mixed
     */
    protected function authorizeViaGate(Request $request, Closure $next, string $permission)
    {
        $user = Auth::user();

        if (! $user || ! ($user instanceof Authorizable) || ! $user->can($permission)) {
            throw new PaymentAuthorizationException('Você não tem permissão para executar esta ação.', 403);
        }

        return $next($request);
    }
}
