<?php

namespace Us\PaymentModuleManager\Contracts;

interface PaymentGatewayInterface
{
    public function processPayment(array $data);
}
