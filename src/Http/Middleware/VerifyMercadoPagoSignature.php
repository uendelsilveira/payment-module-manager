<?php

namespace UendelSilveira\PaymentModuleManager\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

class VerifyMercadoPagoSignature
{
    public function handle(Request $request, Closure $next)
    {
        $secret = Config::get('payment.gateways.mercadopago.webhook_secret');

        // Se não houver secret configurado, pula a verificação (não recomendado para produção)
        if (empty($secret)) {
            return $next($request);
        }

        $signatureHeader = $request->header('x-signature');

        if (! $signatureHeader) {
            abort(403, 'Mercado Pago signature header not found.');
        }

        // Extrai o timestamp (ts) e o hash (v1) do header
        parse_str(str_replace(',', '&', $signatureHeader), $signatureParts);
        $ts = $signatureParts['ts'] ?? null;
        $hash = $signatureParts['v1'] ?? null;

        if (! $ts || ! $hash) {
            abort(403, 'Invalid Mercado Pago signature format.');
        }

        // Cria a string base para o HMAC
        $manifest = "id:{$request->input('data.id')};request-id:{$ts};ts:{$ts};".$request->getContent();

        // Gera a assinatura esperada
        $expectedSignature = hash_hmac('sha256', $manifest, $secret);

        // Compara as assinaturas
        if (! hash_equals($expectedSignature, $hash)) {
            abort(403, 'Invalid Mercado Pago signature.');
        }

        return $next($request);
    }
}
