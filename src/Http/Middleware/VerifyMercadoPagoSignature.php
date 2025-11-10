<?php

namespace UendelSilveira\PaymentModuleManager\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use UendelSilveira\PaymentModuleManager\Exceptions\WebhookSignatureException;

class VerifyMercadoPagoSignature
{
    /**
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $secret = Config::get('payment.gateways.mercadopago.webhook_secret');
        $requireSignature = Config::get('payment.webhook.require_signature', true);

        if (empty($secret)) {
            if (app()->environment('production') || $requireSignature) {
                throw new WebhookSignatureException('Webhook secret não configurado.', 500);
            }

            Log::warning('[VerifyMercadoPagoSignature] Webhook recebido sem validação de assinatura. Não recomendado!');

            return $next($request);
        }

        if (! is_string($secret)) {
            throw new WebhookSignatureException('Webhook secret configurado não é uma string.', 500);
        }

        $signatureHeader = $request->header('x-signature');

        if (! $signatureHeader) {
            throw new WebhookSignatureException('Mercado Pago signature header not found.', 403);
        }

        $headerString = is_array($signatureHeader) ? implode(',', $signatureHeader) : (string) $signatureHeader;
        parse_str(str_replace(',', '&', $headerString), $signatureParts);
        $ts = $signatureParts['ts'] ?? null;
        $hash = $signatureParts['v1'] ?? null;

        if (! is_string($ts) || ! is_string($hash) || $ts === '' || $hash === '') {
            throw new WebhookSignatureException('Invalid Mercado Pago signature format.', 403);
        }

        $this->validateTimestamp($ts);

        $dataId = $request->input('data.id');

        if (! is_string($dataId)) {
            throw new WebhookSignatureException('data.id não encontrado ou inválido no payload do webhook.', 400);
        }

        $manifest = sprintf('id:%s;request-id:%s;ts:%s;%s', $dataId, $ts, $ts, $request->getContent());
        $expectedSignature = hash_hmac('sha256', $manifest, $secret);

        if (! hash_equals($expectedSignature, $hash)) {
            throw new WebhookSignatureException('Invalid Mercado Pago signature.', 403);
        }

        return $next($request);
    }

    private function validateTimestamp(string $ts): void
    {
        $maxAge = Config::get('payment.webhook.max_age_seconds', 300);
        $currentTime = time();
        $timestampAge = $currentTime - (int) $ts;

        if ($timestampAge > $maxAge) {
            throw new WebhookSignatureException('Webhook signature expired. Request is too old.', 403);
        }

        if ($timestampAge < -60) {
            throw new WebhookSignatureException('Webhook signature timestamp is in the future.', 403);
        }
    }
}
