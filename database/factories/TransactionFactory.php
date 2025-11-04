<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 04/11/2025 16:09:37
*/

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
        $paymentMethodId = $this->faker->randomElement(['pix', 'credit_card', 'boleto']);
        $status = $this->faker->randomElement(['pending', 'approved', 'failed']);

        return [
            'gateway' => PaymentGateway::MERCADOPAGO,
            'external_id' => 'mp_'.$this->faker->unique()->lexify('????????????'),
            'amount' => $this->faker->randomFloat(2, 10, 500),
            'description' => $description,
            'status' => $status,
            'metadata' => [
                'method' => PaymentGateway::MERCADOPAGO,
                'description' => $description,
                'payer_email' => $payerEmail,
                'payment_method_id' => $paymentMethodId,
                'payer' => [
                    'email' => $payerEmail,
                ],
            ],
            'retries_count' => 0,
            'last_attempt_at' => null,
            'created_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'updated_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ];
    }
}
