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

final class PaymentNotFoundException extends Exception
{
    public function __construct(string $message = 'Payment not found', int $code = 404)
    {
        parent::__construct($message, $code);
    }
}
