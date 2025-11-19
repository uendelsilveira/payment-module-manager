<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created at: 18/11/25
*/

namespace UendelSilveira\PaymentModuleManager\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use UendelSilveira\PaymentModuleManager\Models\Refund;
use UendelSilveira\PaymentModuleManager\Models\Transaction;

/**
 * @extends Factory<Refund>
 */
class RefundFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Refund>
     */
    protected $model = Refund::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transaction_id' => Transaction::factory(), // Use Transaction factory
            'amount' => fake()->randomFloat(2, 0, 100),
            'reason' => fake()->sentence(3),
            'status' => 'completed',
            'gateway_refund_id' => fake()->uuid(),
            'gateway_response' => [],
            'processed_at' => now(),
        ];
    }
}
