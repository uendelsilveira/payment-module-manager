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

    protected SettingsRepositoryInterface $settingsRepository;

    public function __construct(SettingsRepositoryInterface $settingsRepository)
    {
        $this->settingsRepository = $settingsRepository;
    }

    public function getMercadoPagoSettings()
    {
        $settings = [
            'public_key' => $this->settingsRepository->get('mercadopago_public_key'),
            'access_token' => $this->settingsRepository->get('mercadopago_access_token'),
            'webhook_secret' => $this->settingsRepository->get('mercadopago_webhook_secret'),
        ];

        return $this->successResponse($settings);
    }

    public function saveMercadoPagoSettings(Request $request)
    {
        $request->validate([
            'public_key' => ['nullable', 'string'],
            'access_token' => ['nullable', 'string'],
            'webhook_secret' => ['nullable', 'string'],
        ]);

        $this->settingsRepository->set('mercadopago_public_key', $request->input('public_key'));
        $this->settingsRepository->set('mercadopago_access_token', $request->input('access_token'));
        $this->settingsRepository->set('mercadopago_webhook_secret', $request->input('webhook_secret'));

        return $this->successResponse(null, 'Configurações salvas com sucesso.');
    }

    public function redirectToMercadoPago()
    {
        $clientId = Config::get('payment.gateways.mercadopago.client_id');
        $redirectUri = route('connect.mercadopago.callback');

        $url = "https://auth.mercadopago.com.br/authorization?client_id={$clientId}&response_type=code&platform_id=mp&redirect_uri={$redirectUri}";

        return redirect()->away($url);
    }

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

            $this->settingsRepository->set('mercadopago_access_token', $data['access_token']);
            $this->settingsRepository->set('mercadopago_public_key', $data['public_key']);
            // Opcional: Salvar refresh_token, user_id, etc.
            $this->settingsRepository->set('mercadopago_refresh_token', $data['refresh_token']);
            $this->settingsRepository->set('mercadopago_user_id', $data['user_id']);

            // Redireciona para uma página de sucesso no frontend da aplicação
            return redirect('/')->with('status', 'Conta do Mercado Pago conectada com sucesso!');

        } catch (\Exception $e) {
            return response('Falha ao obter o token de acesso: '.$e->getMessage(), 500);
        }
    }
}
