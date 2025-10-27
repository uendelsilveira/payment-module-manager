<?php

namespace Us\PaymentModuleManager\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponseTrait
{
    /**
     * Retorna uma resposta de sucesso padronizada.
     *
     * @param mixed $data
     * @param string|null $message
     * @param int $statusCode
     * @return JsonResponse
     */
    protected function successResponse($data, string $message = null, int $statusCode = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }

    /**
     * Retorna uma resposta de erro padronizada.
     *
     * @param string|null $message
     * @param int $statusCode
     * @param mixed|null $errors
     * @return JsonResponse
     */
    protected function errorResponse(string $message = null, int $statusCode = 400, $errors = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $statusCode);
    }
}
