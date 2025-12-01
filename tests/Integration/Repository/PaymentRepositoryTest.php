<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created at: 24/11/25
*/

declare(strict_types=1);

namespace Tests\Integration\Repository;

use Illuminate\Database\DatabaseManager;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use UendelSilveira\PaymentModuleManager\Exceptions\PaymentNotFoundException;
use UendelSilveira\PaymentModuleManager\Repositories\PaymentRepository;

final class PaymentRepositoryTest extends TestCase
{
    private PaymentRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        DatabaseManager::init();
        DatabaseManager::runMigrations();

        $this->repository = new PaymentRepository;
    }

    public function test_can_save_and_retrieve_payment(): void
    {
        $paymentId = Uuid::uuid4()->toString();

        $payment = [
            'id' => $paymentId,
            'gateway' => 'stripe',
            'gateway_payment_id' => 'pi_test_123',
            'amount' => 100.00,
            'currency' => 'USD',
            'status' => 'pending',
            'payment_method' => 'card',
            'customer' => [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ],
        ];

        $this->repository->save($payment);

        $retrieved = $this->repository->findById($paymentId);

        $this->assertNotNull($retrieved);
        $this->assertEquals($paymentId, $retrieved['id']);
        $this->assertEquals('stripe', $retrieved['gateway']);
        $this->assertEquals(100.00, $retrieved['amount']);
    }

    public function test_can_update_payment_status(): void
    {
        $paymentId = Uuid::uuid4()->toString();

        $payment = [
            'id' => $paymentId,
            'gateway' => 'stripe',
            'amount' => 50.00,
            'currency' => 'USD',
            'status' => 'pending',
            'payment_method' => 'card',
            'customer' => ['name' => 'Test'],
        ];

        $this->repository->save($payment);

        $this->repository->update($paymentId, ['status' => 'completed']);

        $updated = $this->repository->findById($paymentId);

        $this->assertEquals('completed', $updated['status']);
    }

    public function test_throws_exception_when_payment_not_found(): void
    {
        $this->expectException(PaymentNotFoundException::class);

        $this->repository->update('non-existent-id', ['status' => 'completed']);
    }

    protected function tearDown(): void
    {
        DatabaseManager::rollbackMigrations();
        parent::tearDown();
    }
}
