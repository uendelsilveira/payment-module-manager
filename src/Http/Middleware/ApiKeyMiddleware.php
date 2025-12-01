<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created at: 24/11/25
*/

namespace UendelSilveira\PaymentModuleManager\Http\Middleware;

use Illuminate\Support\Facades\Config;
use UendelSilveira\PaymentModuleManager\Http\ApiResponse;
use UendelSilveira\PaymentModuleManager\Logger\PaymentLogger;

class ApiKeyMiddleware
{
    public function __construct() {}

    public function handle(): void
    {
        $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? null;

        if ($apiKey === null) {
            PaymentLogger::logSecurityEvent('Missing API key');

            header('Content-Type: application/json');
            echo json_encode(ApiResponse::unauthorized('API key is required'));
            exit;
        }

        $validApiKey = Config::get('API_KEY');

        if ($apiKey !== $validApiKey) {
            PaymentLogger::logSecurityEvent('Invalid API key', [
                'provided_key' => substr($apiKey, 0, 8).'...',
            ]);

            header('Content-Type: application/json');
            echo json_encode(ApiResponse::unauthorized('Invalid API key'));
            exit;
        }
    }
}
