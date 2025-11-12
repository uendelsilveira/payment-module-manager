<?php

namespace UendelSilveira\PaymentModuleManager\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use UendelSilveira\PaymentModuleManager\PaymentGatewayManager;
use UendelSilveira\PaymentModuleManager\Traits\ApiResponseTrait;

class SettingsController extends Controller
{
    use ApiResponseTrait;

    public function __construct(protected PaymentGatewayManager $gatewayManager) {}

    public function getSettings(string $gateway): JsonResponse
    {
        try {
            $gatewayInstance = $this->gatewayManager->gateway($gateway);
            $settings = $gatewayInstance->getConfig();

            return $this->successResponse($settings);
        } catch (\InvalidArgumentException $invalidArgumentException) {
            return $this->errorResponse($invalidArgumentException->getMessage(), 404);
        }
    }

    public function saveSettings(Request $request, string $gateway): JsonResponse
    {
        // TODO: Implementar salvamento de configurações quando necessário
        return $this->errorResponse('Funcionalidade de salvamento de configurações ainda não implementada.', 501);
    }

    public function redirectToGateway(string $gateway): RedirectResponse
    {
        // TODO: Implementar redirecionamento para autorização quando necessário
        return redirect('/')->with('error', 'Funcionalidade de autorização ainda não implementada.');
    }

    public function handleGatewayCallback(Request $request, string $gateway): RedirectResponse
    {
        // TODO: Implementar callback de autorização quando necessário
        return redirect('/')->with('error', 'Funcionalidade de callback ainda não implementada.');
    }
}
