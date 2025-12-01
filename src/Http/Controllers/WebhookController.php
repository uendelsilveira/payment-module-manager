<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 19/11/2025 20:38:00
*/

namespace UendelSilveira\PaymentModuleManager\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Throwable;
use UendelSilveira\PaymentModuleManager\Services\WebhookService;
use UendelSilveira\PaymentModuleManager\Traits\ApiResponseTrait;

class WebhookController extends Controller
{
    use ApiResponseTrait;

    public function __construct(protected WebhookService $webhookService) {}

    /**
     * Handle incoming webhook from payment gateway
     */
    public function handle(Request $request, string $gateway): JsonResponse
    {
        try {
            $result = $this->webhookService->handleWebhook($gateway, $request);

            return $this->successResponse($result, 'Webhook handled successfully.');

        } catch (Throwable $e) {
            Log::error('Webhook processing failed', [
                'gateway' => $gateway,
                'exception' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Webhook processing failed: '.$e->getMessage(),
                500
            );
        }
    }
}
