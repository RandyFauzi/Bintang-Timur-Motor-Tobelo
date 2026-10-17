<?php

namespace Database\Factories;

use App\Models\ShippingService;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ShippingService> */
class ShippingServiceFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->unique()->company().' Cargo', 'duration_days' => 14, 'is_active' => true];
    }
}
