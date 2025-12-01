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
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use UendelSilveira\PaymentModuleManager\Facades\PaymentGateway;

class WebhookValidationMiddleware
{
    public function handle(Request $request, Closure $next, string $gateway): Response
    {
        if ($gateway === 'mercadopago') {
            $mpConfig = PaymentGateway::gateway('mercadopago')->getConfig();
            $webhookSecret = $mpConfig['webhook_secret'] ?? null;

            if (! $webhookSecret) {
                Log::warning('Mercado Pago webhook secret not configured. Skipping signature validation.', ['gateway' => $gateway]);
                // Dependendo do nível de segurança desejado, você pode abortar aqui.
                // Por enquanto, vamos permitir que passe, mas é um risco.
            } else {
                $signature = $request->header('x-signature');
                $requestBody = $request->getContent();

                if (! $signature) {
                    Log::warning('Mercado Pago webhook received without x-signature header.', ['gateway' => $gateway]);

                    return response('Unauthorized: Missing signature', Response::HTTP_UNAUTHORIZED);
                }

                // O formato da assinatura do Mercado Pago é geralmente "v1=hash"
                $parts = explode('=', $signature, 2);

                if (count($parts) !== 2 || $parts[0] !== 'v1') {
                    Log::warning('Mercado Pago webhook received with invalid x-signature format.', ['gateway' => $gateway, 'signature' => $signature]);

                    return response('Unauthorized: Invalid signature format', Response::HTTP_UNAUTHORIZED);
                }

                $expectedSignature = hash_hmac('sha256', $requestBody, $webhookSecret);

                if (! hash_equals($expectedSignature, $parts[1])) {
                    Log::warning('Mercado Pago webhook signature validation failed.', [
                        'gateway' => $gateway,
                        'received_signature' => $signature,
                        'expected_hash' => $expectedSignature,
                        'request_body_length' => strlen($requestBody),
                        'request_ip' => $request->ip(),
                    ]);

                    return response('Unauthorized: Invalid signature', Response::HTTP_UNAUTHORIZED);
                }
                Log::info('Mercado Pago webhook signature validated successfully.', ['gateway' => $gateway]);
            }
        }
        // Adicionar lógica para outros gateways aqui, se necessário (ex: Stripe)

        return $next($request);
    }
}
