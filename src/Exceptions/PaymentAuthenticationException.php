<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 04/11/2025 16:09:38
*/

namespace UendelSilveira\PaymentModuleManager\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class PaymentAuthenticationException extends HttpException
{
    public function __construct(string $message = 'Não autenticado', int $statusCode = 401)
    {
        parent::__construct($statusCode, $message);
    }
}
