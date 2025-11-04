<?php

namespace UendelSilveira\PaymentModuleManager\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class PaymentAuthorizationException extends HttpException
{
    public function __construct(string $message = 'Você não tem permissão para esta ação', int $statusCode = 403)
    {
        parent::__construct($statusCode, $message);
    }
}
