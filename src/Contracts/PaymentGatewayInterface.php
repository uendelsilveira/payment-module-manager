<?php
/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 27/10/2025 13:59:40
*/

namespace Us\PaymentModuleManager\Contracts;

interface PaymentGatewayInterface
{
    public function processPayment(array $data);
}
