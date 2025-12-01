<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 19/11/2025 14:24:12
*/

namespace UendelSilveira\PaymentModuleManager\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use UendelSilveira\PaymentModuleManager\Facades\PaymentGateway;

class WebhookValidationMiddleware
{
    public function handle(Request $request, Closure $next, string $gateway): Response
    {
        // A lógica de validação da assinatura do webhook deve ser implementada aqui,
        // ou o método handleWebhook no gateway deve ser responsável por isso.
        // Por enquanto, apenas chamamos o método para garantir a conformidade da interface.
        // PaymentGateway::gateway($gateway)->handleWebhook($request->all());

        return $next($request);
    }
}
