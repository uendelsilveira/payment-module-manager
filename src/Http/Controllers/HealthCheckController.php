<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 04/11/2025 16:09:38
*/

namespace UendelSilveira\PaymentModuleManager\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use UendelSilveira\PaymentModuleManager\PaymentGatewayManager;
use UendelSilveira\PaymentModuleManager\Traits\ApiResponseTrait;

class HealthCheckController
{
    use ApiResponseTrait;

    public function __construct(
        private readonly PaymentGatewayManager $paymentGatewayManager
    ) {}

    public function check(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
        ];

        // Check all configured gateways dynamically
        $configuredGateways = Config::get('payment.gateways', []);

        foreach ($configuredGateways as $gatewayName => $gatewayConfig) {
            $checks[$gatewayName.'_api'] = $this->checkGatewayApi($gatewayName);
        }

        $allHealthy = collect($checks)->every(fn ($check): bool => $check['status'] === 'healthy');
        $status = $allHealthy ? 'healthy' : 'degraded';

        return $this->successResponse([
            'status' => $status,
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
        ], null, $allHealthy ? 200 : 503);
    }

    /**
     * @return array<string, mixed>
     */
    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            DB::connection()->table('transactions')->limit(1)->count();

            return [
                'status' => 'healthy',
                'message' => 'Database connection is working',
            ];
        } catch (\Exception $exception) {
            return [
                'status' => 'unhealthy',
                'message' => 'Database connection failed',
                'error' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function checkCache(): array
    {
        try {
            $testKey = 'health_check_'.time();
            $testValue = 'test';

            Cache::put($testKey, $testValue, 10);
            $retrieved = Cache::get($testKey);
            Cache::forget($testKey);

            if ($retrieved === $testValue) {
                return [
                    'status' => 'healthy',
                    'message' => 'Cache is working',
                ];
            }

            return [
                'status' => 'unhealthy',
                'message' => 'Cache read/write failed',
            ];
        } catch (\Exception $exception) {
            return [
                'status' => 'unhealthy',
                'message' => 'Cache connection failed',
                'error' => $exception->getMessage(),
            ];
        }
    }

    /**
     * Checks the API connectivity for a given payment gateway.
     *
     * @param string $gatewayName The name of the gateway to check (e.g., 'mercadopago').
     *
     * @return array<string, mixed>
     */
    private function checkGatewayApi(string $gatewayName): array
    {
        try {
            $gatewayInstance = $this->paymentGatewayManager->gateway($gatewayName);

            if ($gatewayInstance->checkConnection()) {
                return [
                    'status' => 'healthy',
                    'message' => sprintf('%s API is reachable', ucfirst($gatewayName)),
                ];
            }

            return [
                'status' => 'unhealthy',
                'message' => sprintf('%s API connection failed (checkConnection returned false)', ucfirst($gatewayName)),
            ];
        } catch (\Exception $exception) {
            return [
                'status' => 'unhealthy',
                'message' => sprintf('%s API connection failed', ucfirst($gatewayName)),
                'error' => $exception->getMessage(),
            ];
        }
    }
}
