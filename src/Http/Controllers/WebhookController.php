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
use UendelSilveira\PaymentModuleManager\Jobs\ProcessWebhookJob;
use UendelSilveira\PaymentModuleManager\Traits\ApiResponseTrait;

class WebhookController extends Controller
{
    use ApiResponseTrait;

    /**
     * Handle incoming webhook from payment gateway
     */
    public function handle(Request $request, string $gateway): JsonResponse
    {
        Log::info('Webhook received', ['gateway' => $gateway]);

        ProcessWebhookJob::dispatch($gateway, $request->all());

        return $this->successResponse([], 'Webhook received and queued for processing.');
    }
}
