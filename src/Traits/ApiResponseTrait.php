<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 28/10/2025 20:43:22
*/

namespace Us\PaymentModuleManager\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponseTrait
{
    /**
     * Retorna uma resposta de sucesso padronizada.
     *
     * @param mixed $data
     */
    protected function successResponse($data, ?string $message = null, int $statusCode = 200): JsonResponse
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
     * @param mixed|null $errors
     */
    protected function errorResponse(?string $message = null, int $statusCode = 400, $errors = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $statusCode);
    }
}
