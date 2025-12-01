<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created at: 24/11/25
*/

declare(strict_types=1);

namespace UendelSilveira\PaymentModuleManager\Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use UendelSilveira\PaymentModuleManager\Http\ApiResponse;

final class ApiResponseTest extends TestCase
{
    public function test_success_response(): void
    {
        $response = ApiResponse::success(
            data: ['id' => '123', 'amount' => 100.00],
            status: 201
        );

        $this->assertTrue($response['success']);
        $this->assertEquals('123', $response['data']['id']);
        $this->assertEquals(100.00, $response['data']['amount']);
        $this->assertArrayHasKey('timestamp', $response);
    }

    public function test_error_response(): void
    {
        $response = ApiResponse::error(
            message: 'Test error',
            code: 'TEST_ERROR',
            status: 400
        );

        $this->assertFalse($response['success']);
        $this->assertEquals('Test error', $response['error']['message']);
        $this->assertEquals('TEST_ERROR', $response['error']['code']);
        $this->assertArrayHasKey('trace_id', $response['error']);
    }

    public function test_validation_error_response(): void
    {
        $errors = [
            'email' => ['Email is required', 'Email must be valid'],
            'amount' => ['Amount must be greater than 0'],
        ];

        $response = ApiResponse::validationError($errors);

        $this->assertFalse($response['success']);
        $this->assertEquals('VALIDATION_ERROR', $response['error']['code']);
        $this->assertEquals($errors, $response['error']['details']['validation_errors']);
    }

    public function test_not_found_response(): void
    {
        $response = ApiResponse::notFound('Payment not found');

        $this->assertFalse($response['success']);
        $this->assertEquals('NOT_FOUND', $response['error']['code']);
        $this->assertEquals(404, http_response_code());
    }

    public function test_too_many_requests_response(): void
    {
        $response = ApiResponse::tooManyRequests(60);

        $this->assertFalse($response['success']);
        $this->assertEquals('RATE_LIMIT_EXCEEDED', $response['error']['code']);
        $this->assertEquals(60, $response['error']['details']['retry_after']);
    }
}
