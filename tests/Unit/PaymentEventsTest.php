<?php

namespace UendelSilveira\PaymentModuleManager\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery;
use UendelSilveira\PaymentModuleManager\Contracts\MercadoPagoClientInterface;
use UendelSilveira\PaymentModuleManager\Enums\PaymentGateway;
use UendelSilveira\PaymentModuleManager\Events\PaymentFailed;
use UendelSilveira\PaymentModuleManager\Events\PaymentProcessed;
use UendelSilveira\PaymentModuleManager\Events\PaymentStatusChanged;
use UendelSilveira\PaymentModuleManager\Models\Transaction;
use UendelSilveira\PaymentModuleManager\Tests\TestCase;

class PaymentEventsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_payment_processed_event_is_dispatched_on_success(): void
    {
        Event::fake();

        $this->instance(MercadoPagoClientInterface::class, Mockery::mock(MercadoPagoClientInterface::class, function ($mock) {
            $mock->shouldReceive('createPayment')->andReturn((object) [
                'id' => 'mp_event_test_success',
                'status' => 'approved',
                'transaction_amount' => 100.00,
                'description' => 'Event test payment',
                'payment_method_id' => 'pix',
                'status_detail' => 'accredited',
                'metadata' => (object) [],
            ]);
        }));

        $payload = [
            'amount' => 100.00,
            'method' => PaymentGateway::MERCADOPAGO,
            'description' => 'Event test payment',
            'payer_email' => 'test@example.com',
            'payment_method_id' => 'pix',
        ];

        $response = $this->postJson(route('payment.process'), $payload);

        $response->assertStatus(201);

        Event::assertDispatched(PaymentProcessed::class, function ($event) {
            return $event->transaction->status === 'approved'
                && $event->gatewayResponse['id'] === 'mp_event_test_success';
        });
    }

    public function test_payment_failed_event_is_dispatched_on_failure(): void
    {
        Event::fake();

        $this->instance(MercadoPagoClientInterface::class, Mockery::mock(MercadoPagoClientInterface::class, function ($mock) {
            $mock->shouldReceive('createPayment')
                ->andThrow(new \Exception('Payment gateway error'));
        }));

        $payload = [
            'amount' => 100.00,
            'method' => PaymentGateway::MERCADOPAGO,
            'description' => 'Event test payment failure',
            'payer_email' => 'test@example.com',
            'payment_method_id' => 'pix',
        ];

        try {
            $this->postJson(route('payment.process'), $payload);
        } catch (\Exception $e) {
            // Expected to fail
        }

        Event::assertDispatched(PaymentFailed::class, function ($event) {
            return $event->transaction->status === 'failed'
                && $event->exception instanceof \Exception;
        });
    }

    public function test_payment_status_changed_event_is_dispatched_on_status_change(): void
    {
        Event::fake();

        $transaction = Transaction::create([
            'gateway' => PaymentGateway::MERCADOPAGO,
            'external_id' => 'mp_status_change_test',
            'amount' => 100.00,
            'description' => 'Status change test',
            'status' => 'pending',
            'metadata' => ['payment_method_id' => 'pix'],
        ]);

        $this->instance(MercadoPagoClientInterface::class, Mockery::mock(MercadoPagoClientInterface::class, function ($mock) {
            $mock->shouldReceive('getPayment')
                ->with('mp_status_change_test')
                ->andReturn((object) [
                    'id' => 'mp_status_change_test',
                    'status' => 'approved', // Status changed from pending to approved
                    'transaction_amount' => 100.00,
                    'description' => 'Status change test',
                    'payment_method_id' => 'pix',
                    'status_detail' => 'accredited',
                    'metadata' => (object) [],
                ]);
        }));

        $response = $this->getJson(route('payment.show', ['transaction' => $transaction->id]));

        $response->assertStatus(200);

        Event::assertDispatched(PaymentStatusChanged::class, function ($event) {
            return $event->oldStatus === 'pending'
                && $event->newStatus === 'approved';
        });
    }

}
