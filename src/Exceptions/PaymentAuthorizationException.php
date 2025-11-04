<?php

namespace UendelSilveira\PaymentModuleManager\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class PaymentAuthorizationException extends HttpException
{
    public function __construct(string $message = 'You do not have permission for this action', int $statusCode = 403)
    {
        parent::__construct($statusCode, $message);
    }
}
