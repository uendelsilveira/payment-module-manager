<?php

namespace Us\PaymentModuleManager\Tests\Unit;

use Us\PaymentModuleManager\Contracts\TransactionRepositoryInterface;
use Us\PaymentModuleManager\Services\GatewayManager;
use Us\PaymentModuleManager\Services\PaymentService;
use Us\PaymentModuleManager\Tests\TestCase;

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
