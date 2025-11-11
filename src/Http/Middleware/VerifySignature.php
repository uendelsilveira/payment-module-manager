<?php

namespace UendelSilveira\PaymentModuleManager\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use UendelSilveira\PaymentModuleManager\Exceptions\WebhookSignatureException;

class VerifySignature
{
    /**
     * Handle an incoming request.
     *
     * @param \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse) $next
     *
     * @throws \UendelSilveira\PaymentModuleManager\Exceptions\WebhookSignatureException
     *
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // Tenta obter o nome do gateway da rota.
        // Isso assume que suas rotas de webhook são estruturadas como /webhook/{gatewayName}
        $gatewayName = $request->route('gateway');

        if (! $gatewayName) {
            // Se o nome do gateway não estiver na rota, tenta obtê-lo de outra forma ou lança um erro.
            // Para uma solução mais robusta, você pode tentar extrair de um cabeçalho ou corpo da requisição.
            throw new WebhookSignatureException('Gateway name not provided in webhook route. Cannot verify signature.', 400);
        }

        $secret = Config::get(sprintf('payment.gateways.%s.webhook_secret', $gatewayName));
        $requireSignature = Config::get('payment.webhook.require_signature', true);

        if (empty($secret)) {
            if (app()->environment('production') || $requireSignature) {
                throw new WebhookSignatureException(sprintf("Webhook secret for gateway '%s' not configured.", $gatewayName), 500);
            }

            Log::warning(sprintf("[VerifySignature] Webhook for gateway '%s' received without signature validation. Not recommended!", $gatewayName));

            return $next($request);
        }

        if (! is_string($secret)) {
            throw new WebhookSignatureException(sprintf("Webhook secret for gateway '%s' is not a string.", $gatewayName), 500);
        }

        // O nome do cabeçalho da assinatura ('x-signature') pode ser específico do gateway.
        // Para generalizar, você precisaria de uma forma de configurar isso por gateway.
        $signatureHeader = $request->header('x-signature');

        if (! $signatureHeader) {
            throw new WebhookSignatureException('Webhook signature header not found.', 403);
        }

        $headerString = is_array($signatureHeader) ? implode(',', $signatureHeader) : (string) $signatureHeader;
        parse_str(str_replace(',', '&', $headerString), $signatureParts);
        $ts = $signatureParts['ts'] ?? null;
        $hash = $signatureParts['v1'] ?? null;

        if (! is_string($ts) || ! is_string($hash) || $ts === '' || $hash === '') {
            throw new WebhookSignatureException('Invalid webhook signature format.', 403);
        }

        $this->validateTimestamp($ts);

        // ATENÇÃO: A extração de 'data.id' e a construção do manifest
        // são altamente específicas do payload de webhook de alguns gateways.
        // Para uma solução verdadeiramente genérica, esta parte precisaria ser abstraída
        // (por exemplo, através de um padrão Strategy onde cada gateway forneceria sua própria
        // lógica de construção de manifest e extração de dados).
        $dataId = $request->input('data.id');

        if (! is_string($dataId)) {
            throw new WebhookSignatureException('data.id not found or invalid in webhook payload. This might be specific to the gateway.', 400);
        }

        $manifest = sprintf('id:%s;request-id:%s;ts:%s;%s', $dataId, $ts, $ts, $request->getContent());
        $expectedSignature = hash_hmac('sha256', $manifest, $secret);

        if (! hash_equals($expectedSignature, $hash)) {
            throw new WebhookSignatureException('Invalid webhook signature.', 403);
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
