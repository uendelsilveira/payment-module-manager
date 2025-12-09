<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created at: 11/11/25
*/

namespace UendelSilveira\PaymentModuleManager\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolvePaymentGateway
{
    public function handle(Request $request, Closure $next): Response
    {
        $gateways = config('payment.gateways', []);
        $gateways = is_array($gateways) ? $gateways : [];

        $supported = array_keys($gateways);

        $defaultConfig = config('payment.default_gateway');
        $default = is_string($defaultConfig) ? $defaultConfig : '';

        // Priorizar 'gateway', usar 'method' como fallback (retrocompatibilidade)
        $gatewayInput = $request->input('gateway');
        $methodInput = $request->input('method');
        $chosenInput = is_string($gatewayInput) ? $gatewayInput : (is_string($methodInput) ? $methodInput : $default);
        $chosen = is_string($chosenInput) ? $chosenInput : '';

        if ($chosen === '') {
            return response()->json([
                'error' => 'gateway_not_configured',
                'message' => 'No payment gateway specified. Set PAYMENT_DEFAULT_GATEWAY or include "gateway" in request.',
            ], 422);
        }

        if (! in_array($chosen, $supported, true)) {
            return response()->json([
                'error' => 'gateway_not_supported',
                'message' => 'Payment gateway not supported.',
                'gateway' => $chosen,
                'supported_gateways' => $supported,
            ], 422);
        }

        // Padronizar como 'gateway' para uso interno
        $request->merge(['gateway' => $chosen]);

        return $next($request);
    }
}
