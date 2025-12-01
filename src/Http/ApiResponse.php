<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created at: 24/11/25
*/

declare(strict_types=1);

namespace UendelSilveira\PaymentModuleManager\Http;

use JsonSerializable;

final class ApiResponse implements JsonSerializable
{
    /**
     * @param array<string, mixed>|null $meta
     *
     * @return array<string, mixed>
     */
    public static function success(
        mixed $data,
        int $status = 200,
        ?array $meta = null
    ): array {
        $response = [
            'success' => true,
            'data' => $data,
            'timestamp' => date('c'),
        ];

        if ($meta !== null) {
            $response['meta'] = $meta;
        }

        http_response_code($status);

        return $response;
    }

    /**
     * @param array<string, mixed>|null $details
     *
     * @return array<string, mixed>
     */
    public static function error(
        string $message,
        string $code,
        int $status = 400,
        ?array $details = null
    ): array {
        $response = [
            'success' => false,
            'error' => [
                'message' => $message,
                'code' => $code,
                'trace_id' => self::generateTraceId(),
            ],
            'timestamp' => date('c'),
        ];

        if ($details !== null) {
            $response['error']['details'] = $details;
        }

        http_response_code($status);

        return $response;
    }

    /**
     * @param array<string, array<int, string>> $errors
     *
     * @return array<string, mixed>
     */
    public static function validationError(
        array $errors,
        string $message = 'Validation failed'
    ): array {
        return self::error(
            message: $message,
            code: 'VALIDATION_ERROR',
            status: 422,
            details: ['validation_errors' => $errors]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function notFound(string $message = 'Resource not found'): array
    {
        return self::error(
            message: $message,
            code: 'NOT_FOUND',
            status: 404
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function unauthorized(string $message = 'Unauthorized'): array
    {
        return self::error(
            message: $message,
            code: 'UNAUTHORIZED',
            status: 401
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function tooManyRequests(int $retryAfter): array
    {
        return self::error(
            message: 'Too many requests. Please try again later.',
            code: 'RATE_LIMIT_EXCEEDED',
            status: 429,
            details: ['retry_after' => $retryAfter]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function serverError(
        string $message = 'Internal server error',
        ?string $traceId = null
    ): array {
        return self::error(
            message: $message,
            code: 'INTERNAL_ERROR',
            status: 500,
            details: $traceId ? ['trace_id' => $traceId] : null
        );
    }

    private static function generateTraceId(): string
    {
        return 'trace_'.bin2hex(random_bytes(8));
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [];
    }
}
