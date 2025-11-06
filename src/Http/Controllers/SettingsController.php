<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 28/10/2025 20:43:21
*/

namespace UendelSilveira\PaymentModuleManager\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use UendelSilveira\PaymentModuleManager\Contracts\SettingsRepositoryInterface;
use UendelSilveira\PaymentModuleManager\Traits\ApiResponseTrait;

class SettingsController extends Controller
{
    use ApiResponseTrait;

    public function __construct(protected SettingsRepositoryInterface $settingsRepository) {}

    public function getMercadoPagoSettings(): \Illuminate\Http\JsonResponse
    {
        $publicKey = $this->settingsRepository->get('mercadopago_public_key');
        $accessToken = $this->settingsRepository->get('mercadopago_access_token');
        $webhookSecret = $this->settingsRepository->get('mercadopago_webhook_secret');

        $settings = [
            'public_key' => $publicKey ? $this->maskCredential($publicKey) : null,
            'access_token' => $accessToken ? $this->maskCredential($accessToken) : null,
            'webhook_secret' => $webhookSecret ? $this->maskCredential($webhookSecret) : null,
            'public_key_configured' => ! in_array($publicKey, [null, '', '0'], true),
            'access_token_configured' => ! in_array($accessToken, [null, '', '0'], true),
            'webhook_secret_configured' => ! in_array($webhookSecret, [null, '', '0'], true),
        ];

        return $this->successResponse($settings);
    }

    /**
     * Mascara uma credencial mostrando apenas os primeiros e últimos caracteres.
     */
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

    public function saveMercadoPagoSettings(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'public_key' => ['nullable', 'string'],
            'access_token' => ['nullable', 'string'],
            'webhook_secret' => ['nullable', 'string'],
        ]);

        $publicKey = $request->input('public_key');
        $accessToken = $request->input('access_token');
        $webhookSecret = $request->input('webhook_secret');

        $this->settingsRepository->set('mercadopago_public_key', is_string($publicKey) ? $publicKey : null);
        $this->settingsRepository->set('mercadopago_access_token', is_string($accessToken) ? $accessToken : null);
        $this->settingsRepository->set('mercadopago_webhook_secret', is_string($webhookSecret) ? $webhookSecret : null);

        return $this->successResponse(null, 'Configurações salvas com sucesso.');
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    public function redirectToMercadoPago()
    {
        $clientIdConfig = Config::get('payment.gateways.mercadopago.client_id');
        $clientId = is_string($clientIdConfig) ? $clientIdConfig : '';
        $redirectUri = route('connect.mercadopago.callback');

        $url = sprintf('https://auth.mercadopago.com.br/authorization?client_id=%s&response_type=code&platform_id=mp&redirect_uri=%s', $clientId, $redirectUri);

        return redirect()->away($url);
    }

    /**
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handleMercadoPagoCallback(Request $request)
    {
        $code = $request->input('code');

        if (empty($code)) {
            return response('Erro: Código de autorização não encontrado.', 400);
        }

        try {
            $clientId = Config::get('payment.gateways.mercadopago.client_id');
            $clientSecret = Config::get('payment.gateways.mercadopago.client_secret');
            $redirectUri = route('connect.mercadopago.callback');

            $response = Http::asForm()->post('https://api.mercadopago.com/oauth/token', [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirectUri,
            ]);

            $response->throw();

            $data = $response->json();
            assert(is_array($data));

            $accessToken = is_string($data['access_token'] ?? null) ? $data['access_token'] : null;
            $publicKey = is_string($data['public_key'] ?? null) ? $data['public_key'] : null;
            $refreshToken = is_string($data['refresh_token'] ?? null) ? $data['refresh_token'] : null;
            $userIdRaw = $data['user_id'] ?? null;
            $userId = is_string($userIdRaw) || is_int($userIdRaw) ? (string) $userIdRaw : null;

            $this->settingsRepository->set('mercadopago_access_token', $accessToken);
            $this->settingsRepository->set('mercadopago_public_key', $publicKey);
            // Opcional: Salvar refresh_token, user_id, etc.
            $this->settingsRepository->set('mercadopago_refresh_token', $refreshToken);
            $this->settingsRepository->set('mercadopago_user_id', $userId);

            // Redireciona para uma página de sucesso no frontend da aplicação
            return redirect('/')->with('status', 'Conta do Mercado Pago conectada com sucesso!');

        } catch (\Exception $exception) {
            return response('Falha ao obter o token de acesso: '.$exception->getMessage(), 500);
        }
    }
}
