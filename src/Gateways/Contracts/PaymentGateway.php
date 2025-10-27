<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created at: 27/10/25
*/

namespace Us\PaymentModuleManager\Gateways\Contracts;

class PaymentGateway
{
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }
}
