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
use UendelSilveira\PaymentModuleManager\Exceptions\WebhookProcessingException;
use UendelSilveira\PaymentModuleManager\Facades\PaymentGateway;

class WebhookValidationMiddleware
{
    public function handle(Request $request, Closure $next, string $gateway): Response
    {
        try {
            /** @phpstan-ignore-next-line */
            PaymentGateway::gateway($gateway)->processWebhook($request->all());
        } catch (WebhookProcessingException $webhookProcessingException) {
            return response()->json(['error' => $webhookProcessingException->getMessage()], 400);
        }

        return $next($request);
    }
}
