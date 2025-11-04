<?php

namespace UendelSilveira\PaymentModuleManager\Exceptions;

class PaymentGatewayException extends PaymentModuleException
{
    protected int $statusCode;

    public function __construct(string $message = '', int $statusCode = 400)
    {
        parent::__construct($message, $statusCode);
        $this->statusCode = $statusCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
