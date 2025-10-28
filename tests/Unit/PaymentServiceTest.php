<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 28/10/2025 20:57:39
*/

namespace UendelSilveira\PaymentModuleManager\Tests\Unit;

use UendelSilveira\PaymentModuleManager\Contracts\TransactionRepositoryInterface;
use UendelSilveira\PaymentModuleManager\Services\GatewayManager;
use UendelSilveira\PaymentModuleManager\Services\PaymentService;
use UendelSilveira\PaymentModuleManager\Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    /** @test */
    public function it_can_be_instantiated_and_has_dependencies()
    {
        $gatewayManager = $this->createMock(GatewayManager::class);
        $transactionRepository = $this->createMock(TransactionRepositoryInterface::class);

        $paymentService = new PaymentService($gatewayManager, $transactionRepository);

        $this->assertInstanceOf(PaymentService::class, $paymentService);
    }
}
