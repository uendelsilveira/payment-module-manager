<?php

namespace UendelSilveira\PaymentModuleManager\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use UendelSilveira\PaymentModuleManager\Contracts\MercadoPagoClientInterface;
use UendelSilveira\PaymentModuleManager\Traits\ApiResponseTrait;

class HealthCheckController
{
    use ApiResponseTrait;

    public function __construct(
        private readonly MercadoPagoClientInterface $mercadoPagoClient
    ) {}

    public function check(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'mercadopago_api' => $this->checkMercadoPagoApi(),
        ];

        $allHealthy = collect($checks)->every(fn ($check) => $check['status'] === 'healthy');
        $status = $allHealthy ? 'healthy' : 'degraded';

        return $this->success([
            'status' => $status,
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
        ], $allHealthy ? 200 : 503);
    }

    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            DB::connection()->table('transactions')->limit(1)->count();

            return [
                'status' => 'healthy',
                'message' => 'Database connection is working',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Database connection failed',
                'error' => $e->getMessage(),
            ];
        }
    }

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
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Cache connection failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkMercadoPagoApi(): array
    {
        try {
            // Test API connectivity by making a simple request
            // This is a lightweight check that doesn't create any transactions
            $this->mercadoPagoClient->getPaymentMethods();

            return [
                'status' => 'healthy',
                'message' => 'MercadoPago API is reachable',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'MercadoPago API connection failed',
                'error' => $e->getMessage(),
            ];
        }
    }
}
