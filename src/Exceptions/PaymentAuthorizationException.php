<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 04/11/2025 16:09:38
*/

namespace UendelSilveira\PaymentModuleManager\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class PaymentAuthorizationException extends HttpException
{
    public function __construct(string $message = 'You do not have permission for this action', int $statusCode = 403)
    {
        parent::__construct($statusCode, $message);
    }
}
