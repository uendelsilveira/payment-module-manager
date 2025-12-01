<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created at: 24/11/25
*/

declare(strict_types=1);

namespace UendelSilveira\PaymentModuleManager\Exceptions;

use Exception;

final class TooManyRequestsException extends Exception
{
    public function __construct(string $message = 'Too many requests', int $code = 429)
    {
        parent::__construct($message, $code);
    }
}
