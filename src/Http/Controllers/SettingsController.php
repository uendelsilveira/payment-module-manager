<?php

namespace UendelSilveira\PaymentModuleManager\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use UendelSilveira\PaymentModuleManager\Services\GatewayManager;
use UendelSilveira\PaymentModuleManager\Traits\ApiResponseTrait;

class SettingsController extends Controller
{
    use ApiResponseTrait;

    public function __construct(protected GatewayManager $gatewayManager) {}

    public function getSettings(string $gateway): JsonResponse
    {
        try {
            $gatewayInstance = $this->gatewayManager->create($gateway);
            $settings = $gatewayInstance->getSettings();

            return $this->successResponse($settings);
        } catch (\InvalidArgumentException $invalidArgumentException) {
            return $this->errorResponse($invalidArgumentException->getMessage(), 404);
        }
    }

    public function saveSettings(Request $request, string $gateway): JsonResponse
    {
        try {
            $gatewayInstance = $this->gatewayManager->create($gateway);

            $request->validate([
                'public_key' => ['sometimes', 'nullable', 'string'],
                'access_token' => ['sometimes', 'nullable', 'string'],
                'webhook_secret' => ['sometimes', 'nullable', 'string'],
            ]);

            $gatewayInstance->saveSettings($request->all());

            return $this->successResponse(null, 'Configurações salvas com sucesso.');
        } catch (\InvalidArgumentException $invalidArgumentException) {
            return $this->errorResponse($invalidArgumentException->getMessage(), 404);
        }
    }

    public function redirectToGateway(string $gateway): RedirectResponse
    {
        try {
            $gatewayInstance = $this->gatewayManager->create($gateway);
            $url = $gatewayInstance->getAuthorizationUrl();

            return redirect()->away($url);
        } catch (\InvalidArgumentException $invalidArgumentException) {
            abort(404, $invalidArgumentException->getMessage());
        }
    }

    public function handleGatewayCallback(Request $request, string $gateway): RedirectResponse
    {
        try {
            $gatewayInstance = $this->gatewayManager->create($gateway);
            $gatewayInstance->handleCallback($request);

            return redirect('/')->with('status', 'Conta do gateway conectada com sucesso!');

        } catch (\InvalidArgumentException $e) {
            abort(404, $e->getMessage());
        } catch (\Exception $e) {
            return redirect('/')->with('error', 'Falha ao conectar a conta do gateway: '.$e->getMessage());
        }
    }
}
