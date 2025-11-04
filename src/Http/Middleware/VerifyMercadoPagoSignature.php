<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 04/11/2025 16:09:38
*/

namespace UendelSilveira\PaymentModuleManager\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use UendelSilveira\PaymentModuleManager\Exceptions\WebhookSignatureException;

class VerifyMercadoPagoSignature
{
    public function handle(Request $request, Closure $next)
    {
        $secret = Config::get('payment.gateways.mercadopago.webhook_secret');
        $requireSignature = Config::get('payment.webhook.require_signature', true);
        $isProduction = app()->environment('production');

        // Em produção, sempre exige o secret configurado
        if ($isProduction && empty($secret)) {
            throw new WebhookSignatureException('Webhook secret não configurado. Configure MERCADOPAGO_WEBHOOK_SECRET no ambiente de produção.', 500);
        }

        // Se não houver secret configurado e não for obrigatório (apenas desenvolvimento)
        if (empty($secret) && ! $requireSignature) {
            \Log::warning('[VerifyMercadoPagoSignature] Webhook recebido sem validação de assinatura. Isso não é recomendado!');

            return $next($request);
        }

        // Se chegou aqui, temos secret e devemos validar
        if (empty($secret)) {
            throw new WebhookSignatureException('Webhook signature validation is required but secret is not configured.', 403);
        }

        $signatureHeader = $request->header('x-signature');

        if (! $signatureHeader) {
            throw new WebhookSignatureException('Mercado Pago signature header not found.', 403);
        }

        // Extrai o timestamp (ts) e o hash (v1) do header
        parse_str(str_replace(',', '&', $signatureHeader), $signatureParts);
        $ts = $signatureParts['ts'] ?? null;
        $hash = $signatureParts['v1'] ?? null;

        if (! $ts || ! $hash) {
            throw new WebhookSignatureException('Invalid Mercado Pago signature format.', 403);
        }

        // Validação de timestamp para prevenir replay attacks
        $maxAge = Config::get('payment.webhook.max_age_seconds', 300); // 5 minutos por padrão
        $currentTime = time();
        $timestampAge = $currentTime - (int) $ts;

        if ($timestampAge > $maxAge) {
            throw new WebhookSignatureException('Webhook signature expired. Request is too old.', 403);
        }

        if ($timestampAge < -60) {
            // Timestamp no futuro (tolerância de 1 minuto para diferenças de relógio)
            throw new WebhookSignatureException('Webhook signature timestamp is in the future.', 403);
        }

        // Cria a string base para o HMAC
        $manifest = "id:{$request->input('data.id')};request-id:{$ts};ts:{$ts};".$request->getContent();

        // Gera a assinatura esperada
        $expectedSignature = hash_hmac('sha256', $manifest, $secret);

        // Compara as assinaturas
        if (! hash_equals($expectedSignature, $hash)) {
            throw new WebhookSignatureException('Invalid Mercado Pago signature.', 403);
        }

        return $next($request);
    }
}
