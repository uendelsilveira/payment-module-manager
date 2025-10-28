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
}
