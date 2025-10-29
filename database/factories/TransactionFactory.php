<?php

namespace UendelSilveira\PaymentModuleManager\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use UendelSilveira\PaymentModuleManager\Enums\PaymentGateway;
use UendelSilveira\PaymentModuleManager\Models\Transaction;

class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        $description = $this->faker->sentence;
        $payerEmail = $this->faker->safeEmail;

        return [
            'gateway' => PaymentGateway::MERCADOPAGO,
            'external_id' => 'mp_' . $this->faker->unique()->lexify('????????????'),
            'amount' => $this->faker->randomFloat(2, 10, 500),
            'description' => $description,
            'status' => 'pending',
            'metadata' => [
                'method' => PaymentGateway::MERCADOPAGO,
                'description' => $description,
                'payer_email' => $payerEmail,
                'payment_method_id' => 'pix',
                'payer' => [
                    'email' => $payerEmail,
                ],
            ],
            'retries_count' => 0,
            'last_attempt_at' => null,
        ];
    }
}
