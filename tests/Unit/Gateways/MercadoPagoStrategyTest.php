<?php

namespace UendelSilveira\PaymentModuleManager\Tests\Unit\Gateways;

use Exception;
use Mockery;
use Mockery\MockInterface;
use UendelSilveira\PaymentModuleManager\Contracts\MercadoPagoClientInterface;
use UendelSilveira\PaymentModuleManager\Gateways\MercadoPagoStrategy;
use UendelSilveira\PaymentModuleManager\Tests\TestCase;

class MercadoPagoStrategyTest extends TestCase
{
    private MockInterface&MercadoPagoClientInterface $mpClientMock;
    private MercadoPagoStrategy $mercadoPagoStrategy;

    protected function setUp(): void
    {
        parent::setUp();
        /** @var MockInterface&MercadoPagoClientInterface $mpClientMock */
        $mpClientMock = Mockery::mock(MercadoPagoClientInterface::class);
        $this->mpClientMock = $mpClientMock;
        $this->mercadoPagoStrategy = new MercadoPagoStrategy($this->mpClientMock);
    }

    public function test_get_payment_methods_successfully(): void
    {
        // Arrange
        $expectedPaymentMethods = [
            (object)['id' => 'pix', 'name' => 'Pix'],
            (object)['id' => 'credit_card', 'name' => 'Credit Card'],
        ];

        $this->mpClientMock
            ->shouldReceive('getPaymentMethods')
            ->once()
            ->andReturn($expectedPaymentMethods);

        // Act
        $paymentMethods = $this->mercadoPagoStrategy->getPaymentMethods();

        // Assert
        $this->assertEquals($expectedPaymentMethods, $paymentMethods);
    }

    public function test_get_payment_methods_throws_exception(): void
    {
        // Arrange
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Erro ao buscar métodos de pagamento: Test Exception');

        $this->mpClientMock
            ->shouldReceive('getPaymentMethods')
            ->once()
            ->andThrow(new Exception('Test Exception'));

        // Act
        $this->mercadoPagoStrategy->getPaymentMethods();
    }
}
