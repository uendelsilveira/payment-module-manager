<?php

namespace UendelSilveira\PaymentModuleManager\Gateways;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use UendelSilveira\PaymentModuleManager\Contracts\GatewayInterface;
use UendelSilveira\PaymentModuleManager\Contracts\MercadoPagoClientInterface;
use UendelSilveira\PaymentModuleManager\Contracts\SettingsRepositoryInterface;
use UendelSilveira\PaymentModuleManager\DTOs\MercadoPagoTokenResponse;

class MercadoPagoGateway implements GatewayInterface
{
    public function __construct(
        protected SettingsRepositoryInterface $settingsRepository,
        protected MercadoPagoClientInterface $client
    ) {}

    // Métodos de Configuração
    public function getName(): string
    {
        return 'Mercado Pago';
    }

    /**
     * @return array<string, mixed>
     */
    public function getSettings(): array
    {
        $publicKey = $this->settingsRepository->get('mercadopago_public_key');
        $accessToken = $this->settingsRepository->get('mercadopago_access_token');
        $webhookSecret = $this->settingsRepository->get('mercadopago_webhook_secret');

        return [
            'public_key' => $publicKey ? $this->maskCredential($publicKey) : null,
            'access_token' => $accessToken ? $this->maskCredential($accessToken) : null,
            'webhook_secret' => $webhookSecret ? $this->maskCredential($webhookSecret) : null,
            'public_key_configured' => ! in_array($publicKey, [null, '', '0'], true),
            'access_token_configured' => ! in_array($accessToken, [null, '', '0'], true),
            'webhook_secret_configured' => ! in_array($webhookSecret, [null, '', '0'], true),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function saveSettings(array $data): void
    {
        $publicKey = $data['public_key'] ?? null;
        $accessToken = $data['access_token'] ?? null;
        $webhookSecret = $data['webhook_secret'] ?? null;

        $this->settingsRepository->set('mercadopago_public_key', is_string($publicKey) ? $publicKey : null);
        $this->settingsRepository->set('mercadopago_access_token', is_string($accessToken) ? $accessToken : null);
        $this->settingsRepository->set('mercadopago_webhook_secret', is_string($webhookSecret) ? $webhookSecret : null);
    }

    public function getAuthorizationUrl(): string
    {
        $clientIdConfig = Config::get('payment.gateways.mercadopago.client_id');
        $clientId = is_string($clientIdConfig) ? $clientIdConfig : '';
        $redirectUri = route('connect.gateway.callback', ['gateway' => 'mercadopago']);

        return sprintf('https://auth.mercadopago.com.br/authorization?client_id=%s&response_type=code&platform_id=mp&redirect_uri=%s', $clientId, $redirectUri);
    }

    public function handleCallback(Request $request): void
    {
        $code = $request->input('code');

        if (empty($code) || ! is_string($code)) {
            throw new \Exception('Código de autorização não encontrado ou inválido.');
        }

        $clientId = Config::get('payment.gateways.mercadopago.client_id');
        $clientSecret = Config::get('payment.gateways.mercadopago.client_secret');
        $redirectUri = route('connect.gateway.callback', ['gateway' => 'mercadopago']);

        $response = Http::asForm()->post('https://api.mercadopago.com/oauth/token', [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ]);

        $response->throw();

        /** @var array<string, mixed>|null $responseData */
        $responseData = $response->json();
        $mercadoPagoTokenResponse = MercadoPagoTokenResponse::fromArray($responseData);

        $this->settingsRepository->set('mercadopago_access_token', $mercadoPagoTokenResponse->accessToken);
        $this->settingsRepository->set('mercadopago_public_key', $mercadoPagoTokenResponse->publicKey);
        $this->settingsRepository->set('mercadopago_refresh_token', $mercadoPagoTokenResponse->refreshToken);
        $this->settingsRepository->set('mercadopago_user_id', (string) $mercadoPagoTokenResponse->userId);
    }

    protected function maskCredential(string $credential, int $visibleChars = 4): string
    {
        $length = strlen($credential);

        if ($length <= $visibleChars * 2) {
            return str_repeat('*', $length);
        }

        $start = substr($credential, 0, $visibleChars);
        $end = substr($credential, -$visibleChars);
        $masked = str_repeat('*', $length - ($visibleChars * 2));

        return $start.$masked.$end;
    }

    // Implementação dos Métodos de Pagamento
    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function charge(float $amount, array $data): array
    {
        return $this->client->createPayment($amount, $data);
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayment(string $externalPaymentId): array
    {
        return $this->client->getPayment($externalPaymentId);
    }

    /**
     * @return array<string, mixed>
     */
    public function refund(string $externalPaymentId, ?float $amount = null): array
    {
        return $this->client->refundPayment($externalPaymentId, $amount);
    }

    /**
     * @return array<string, mixed>
     */
    public function cancel(string $externalPaymentId): array
    {
        return $this->client->cancelPayment($externalPaymentId);
    }
}
