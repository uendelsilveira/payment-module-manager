<?php

namespace UendelSilveira\PaymentModuleManager\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class WebhookSignatureException extends HttpException
{
    public function __construct(string $message = 'Invalid webhook signature', int $statusCode = 403)
    {
        parent::__construct($statusCode, $message);
    }
}
