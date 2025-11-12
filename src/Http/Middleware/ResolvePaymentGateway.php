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
        $methodInput = $request->input('method');
        $gatewayInput = $request->input('gateway');
        $chosenInput = is_string($methodInput) ? $methodInput : (is_string($gatewayInput) ? $gatewayInput : $default);
        $chosen = is_string($chosenInput) ? $chosenInput : '';

        if ($chosen === '') {
            return response()->json([
                'message' => 'No default payment gateway configured. Set PAYMENT_DEFAULT_GATEWAY or provide method/gateway in the request.',
            ], 500);
        }

        if (! in_array($chosen, $supported, true)) {
            return response()->json([
                'message' => 'Payment gateway not supported.',
                'gateway' => $chosen,
                'supported' => $supported,
            ], 422);
        }

        $request->merge([
            'method' => $chosen,
            'gateway' => $chosen,
        ]);

        return $next($request);
    }
}
