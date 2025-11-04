<?php

namespace UendelSilveira\PaymentModuleManager\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

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
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $permission
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $permission)
    {
        $authorizationStrategy = Config::get('payment.authorization.strategy', 'none');

        switch ($authorizationStrategy) {
            case 'callback':
                return $this->authorizeViaCallback($request, $next, $permission);
            
            case 'laravel_gate':
                return $this->authorizeViaGate($request, $next, $permission);
            
            case 'none':
            default:
                // Sem autorização - útil para desenvolvimento
                return $next($request);
        }
    }

    /**
     * Autoriza usando callback customizado.
     */
    protected function authorizeViaCallback(Request $request, Closure $next, string $permission)
    {
        $callback = Config::get('payment.authorization.callback');

        if (! is_callable($callback)) {
            abort(500, 'Callback de autorização não configurado corretamente.');
        }

        $user = auth()->user();
        $result = call_user_func($callback, $user, $permission, $request);

        if ($result !== true) {
            abort(403, 'Você não tem permissão para executar esta ação.');
        }

        return $next($request);
    }

    /**
     * Autoriza usando Laravel Gates.
     */
    protected function authorizeViaGate(Request $request, Closure $next, string $permission)
    {
        if (! auth()->user() || ! auth()->user()->can($permission)) {
            abort(403, 'Você não tem permissão para executar esta ação.');
        }

        return $next($request);
    }
}

