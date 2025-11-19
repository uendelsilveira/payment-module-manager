<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 19/11/2025 14:24:12
*/

namespace UendelSilveira\PaymentModuleManager\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use UendelSilveira\PaymentModuleManager\Models\PaymentGateway;

/** @extends \Illuminate\Database\Eloquent\Factories\Factory<PaymentGateway> */
class PaymentGatewayFactory extends Factory
{
    protected $model = PaymentGateway::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company().' Gateway',
            'code' => $this->faker->unique()->slug(1),
            'config' => [
                'api_key' => $this->faker->uuid(),
                'secret' => $this->faker->sha256(),
                'base_url' => $this->faker->url(),
            ],
            'is_active' => $this->faker->boolean(),
        ];
    }
}
