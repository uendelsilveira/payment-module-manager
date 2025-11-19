<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 19/11/2025 14:24:12
*/

namespace UendelSilveira\PaymentModuleManager\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use UendelSilveira\PaymentModuleManager\Models\WebhookLog;

/** @extends \Illuminate\Database\Eloquent\Factories\Factory<WebhookLog> */
class WebhookLogFactory extends Factory
{
    protected $model = WebhookLog::class;

    public function definition(): array
    {
        return [
            'gateway' => $this->faker->word(),
            'event_type' => $this->faker->randomElement(['payment_created', 'payment_updated', 'payment_failed']),
            'payload' => ['id' => $this->faker->uuid(), 'status' => 'pending'],
            'status' => $this->faker->randomElement(['pending', 'processed', 'failed']),
            'response_code' => $this->faker->numberBetween(200, 500),
            'response_body' => ['message' => $this->faker->sentence()],
            'processed_at' => $this->faker->optional()->dateTime(),
            'retries' => $this->faker->numberBetween(0, 3),
            'last_retry_at' => $this->faker->optional()->dateTime(),
        ];
    }
}
