<?php

namespace UendelSilveira\PaymentModuleManager\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Orchestra\Testbench\TestCase;
use UendelSilveira\PaymentModuleManager\Events\PaymentFailed;
use UendelSilveira\PaymentModuleManager\Events\PaymentStatusChanged;
use UendelSilveira\PaymentModuleManager\Listeners\LogPaymentFailed;
use UendelSilveira\PaymentModuleManager\Listeners\SendPaymentStatusNotification;
use UendelSilveira\PaymentModuleManager\Models\Transaction;
use UendelSilveira\PaymentModuleManager\Providers\PaymentServiceProvider;

class PaymentListenersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Clean up any pending transactions
        while (DB::connection()->transactionLevel() > 0) {
            DB::connection()->rollBack();
        }
    }

    protected function getPackageProviders($app): array
    {
        return [PaymentServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }

    protected function tearDown(): void
    {
        // Ensure no active transactions
        if (\Illuminate\Support\Facades\DB::connection()->transactionLevel() > 0) {
            \Illuminate\Support\Facades\DB::connection()->rollBack();
        }

        parent::tearDown();
    }

    // LogPaymentFailed tests

    public function test_log_payment_failed_logs_error(): void
    {
        Log::shouldReceive('channel')->with('payment')->once()->andReturnSelf();
        Log::shouldReceive('error')->once()->with(
            'Payment processing failed via event',
            \Mockery::type('array')
        );

        /** @var Transaction $transaction */
        $transaction = Transaction::factory()->create(['status' => 'failed']);
        $exception = new \Exception('Payment gateway error');
        $event = new PaymentFailed($transaction, $exception, ['amount' => 100]);

        $listener = new LogPaymentFailed;
        $listener->handle($event);
    }

    public function test_log_payment_failed_masks_sensitive_data(): void
    {
        Log::shouldReceive('channel')->with('payment')->once()->andReturnSelf();
        Log::shouldReceive('error')->once()->with(
            'Payment processing failed via event',
            \Mockery::type('array')
        );

        /** @var Transaction $transaction */
        $transaction = Transaction::factory()->create(['status' => 'failed']);
        $exception = new \Exception('Error');
        $paymentData = [
            'amount' => 100,
            'card_number' => '4111111111111111', // Should be masked
        ];

        $event = new PaymentFailed($transaction, $exception, $paymentData);

        $listener = new LogPaymentFailed;
        $listener->handle($event);
    }

    // SendPaymentStatusNotification tests

    public function test_send_notification_with_webhook_enabled(): void
    {
        Config::set('payment.notifications.webhook_url', 'https://example.com/webhook');
        Config::set('payment.notifications.email.enabled', false);
        Config::set('payment.notifications.sms.enabled', false);

        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->twice();

        Http::fake([
            'example.com/*' => Http::response(['success' => true], 200),
        ]);

        /** @var Transaction $transaction */
        $transaction = Transaction::factory()->create([
            'status' => 'approved',
            'external_id' => 'MP123',
        ]);

        $event = new PaymentStatusChanged($transaction, 'pending', 'approved');

        $listener = new SendPaymentStatusNotification;
        $listener->handle($event);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://example.com/webhook'
                && $request['event'] === 'payment.status_changed'
                && isset($request['transaction_id'])
                && isset($request['new_status']);
        });
    }

    public function test_send_notification_handles_webhook_failure(): void
    {
        Config::set('payment.notifications.webhook_url', 'https://example.com/webhook');
        Config::set('payment.notifications.email.enabled', false);
        Config::set('payment.notifications.sms.enabled', false);

        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->atLeast()->once();
        Log::shouldReceive('warning')->atLeast()->once();

        Http::fake([
            'example.com/*' => Http::response(['error' => 'Server error'], 500),
        ]);

        /** @var Transaction $transaction */
        $transaction = Transaction::factory()->create(['status' => 'approved']);
        $event = new PaymentStatusChanged($transaction, 'pending', 'approved');

        $listener = new SendPaymentStatusNotification;
        $listener->handle($event);
    }

    public function test_send_notification_handles_webhook_exception(): void
    {
        Config::set('payment.notifications.webhook_url', 'https://example.com/webhook');
        Config::set('payment.notifications.email.enabled', false);
        Config::set('payment.notifications.sms.enabled', false);

        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->once();
        Log::shouldReceive('error')->once();

        Http::fake(function () {
            throw new \Exception('Network error');
        });

        /** @var Transaction $transaction */
        $transaction = Transaction::factory()->create(['status' => 'approved']);
        $event = new PaymentStatusChanged($transaction, 'pending', 'approved');

        $listener = new SendPaymentStatusNotification;
        $listener->handle($event);
    }

    public function test_send_notification_skips_webhook_when_not_configured(): void
    {
        Config::set('payment.notifications.webhook_url', null);
        Config::set('payment.notifications.email.enabled', false);
        Config::set('payment.notifications.sms.enabled', false);

        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->once();

        Http::fake();

        /** @var Transaction $transaction */
        $transaction = Transaction::factory()->create(['status' => 'approved']);
        $event = new PaymentStatusChanged($transaction, 'pending', 'approved');

        $listener = new SendPaymentStatusNotification;
        $listener->handle($event);

        Http::assertNothingSent();
    }

    public function test_send_notification_with_email_enabled(): void
    {
        Config::set('payment.notifications.webhook_url', null);
        Config::set('payment.notifications.email.enabled', true);
        Config::set('payment.notifications.email.default_recipient', 'test@example.com');
        Config::set('payment.notifications.sms.enabled', false);

        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->twice();

        /** @var Transaction $transaction */
        $transaction = Transaction::factory()->create([
            'status' => 'approved',
            'metadata' => ['payer_email' => 'customer@example.com'],
        ]);

        $event = new PaymentStatusChanged($transaction, 'pending', 'approved');

        $listener = new SendPaymentStatusNotification;
        $listener->handle($event);
    }

    public function test_send_notification_email_uses_default_recipient_when_no_payer_email(): void
    {
        Config::set('payment.notifications.webhook_url', null);
        Config::set('payment.notifications.email.enabled', true);
        Config::set('payment.notifications.email.default_recipient', 'default@example.com');
        Config::set('payment.notifications.sms.enabled', false);

        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->twice();

        /** @var Transaction $transaction */
        $transaction = Transaction::factory()->create([
            'status' => 'approved',
            'metadata' => [], // No payer_email
        ]);

        $event = new PaymentStatusChanged($transaction, 'pending', 'approved');

        $listener = new SendPaymentStatusNotification;
        $listener->handle($event);
    }

    public function test_send_notification_email_skips_when_no_recipient(): void
    {
        Config::set('payment.notifications.webhook_url', null);
        Config::set('payment.notifications.email.enabled', true);
        Config::set('payment.notifications.email.default_recipient', null);
        Config::set('payment.notifications.sms.enabled', false);

        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->atLeast()->once();
        Log::shouldReceive('warning')->atLeast()->once();

        /** @var Transaction $transaction */
        $transaction = Transaction::factory()->create([
            'status' => 'approved',
            'metadata' => [],
        ]);

        $event = new PaymentStatusChanged($transaction, 'pending', 'approved');

        $listener = new SendPaymentStatusNotification;
        $listener->handle($event);
    }

    public function test_send_notification_with_sms_enabled(): void
    {
        Config::set('payment.notifications.webhook_url', null);
        Config::set('payment.notifications.email.enabled', false);
        Config::set('payment.notifications.sms.enabled', true);
        Config::set('payment.notifications.sms.provider', 'twilio');
        Config::set('payment.notifications.sms.template', 'Payment {status}');

        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->twice();

        /** @var Transaction $transaction */
        $transaction = Transaction::factory()->create([
            'status' => 'approved',
            'metadata' => ['payer_phone' => '+5511999999999'],
        ]);

        $event = new PaymentStatusChanged($transaction, 'pending', 'approved');

        $listener = new SendPaymentStatusNotification;
        $listener->handle($event);
    }

    public function test_send_notification_sms_skips_when_no_phone(): void
    {
        Config::set('payment.notifications.webhook_url', null);
        Config::set('payment.notifications.email.enabled', false);
        Config::set('payment.notifications.sms.enabled', true);

        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->atLeast()->once();
        Log::shouldReceive('warning')->atLeast()->once();

        /** @var Transaction $transaction */
        $transaction = Transaction::factory()->create([
            'status' => 'approved',
            'metadata' => [], // No phone
        ]);

        $event = new PaymentStatusChanged($transaction, 'pending', 'approved');

        $listener = new SendPaymentStatusNotification;
        $listener->handle($event);
    }

    public function test_send_notification_processes_multiple_channels(): void
    {
        Config::set('payment.notifications.webhook_url', 'https://example.com/webhook');
        Config::set('payment.notifications.email.enabled', true);
        Config::set('payment.notifications.email.default_recipient', 'test@example.com');
        Config::set('payment.notifications.sms.enabled', true);

        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->times(4); // Initial + webhook + email + sms

        Http::fake([
            'example.com/*' => Http::response(['success' => true], 200),
        ]);

        /** @var Transaction $transaction */
        $transaction = Transaction::factory()->create([
            'status' => 'approved',
            'metadata' => [
                'payer_email' => 'customer@example.com',
                'payer_phone' => '+5511999999999',
            ],
        ]);

        $event = new PaymentStatusChanged($transaction, 'pending', 'approved');

        $listener = new SendPaymentStatusNotification;
        $listener->handle($event);

        Http::assertSentCount(1);
    }

    public function test_send_notification_includes_transaction_details_in_webhook(): void
    {
        Config::set('payment.notifications.webhook_url', 'https://example.com/webhook');
        Config::set('payment.notifications.email.enabled', false);
        Config::set('payment.notifications.sms.enabled', false);

        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->twice();

        Http::fake();

        /** @var Transaction $transaction */
        $transaction = Transaction::factory()->create([
            'external_id' => 'MP12345',
            'gateway' => 'mercadopago',
            'amount' => 150.00,
            'currency' => 'BRL',
            'status' => 'approved',
        ]);

        $event = new PaymentStatusChanged($transaction, 'pending', 'approved');

        $listener = new SendPaymentStatusNotification;
        $listener->handle($event);

        Http::assertSent(function ($request) {
            return $request['external_id'] === 'MP12345'
                && $request['gateway'] === 'mercadopago'
                && $request['amount'] == 150.00
                && $request['currency'] === 'BRL';
        });
    }
}
