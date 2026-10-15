<?php

namespace Database\Factories;

use App\Models\Distributor;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Distributor> */
class DistributorFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['name' => fake()->unique()->company(), 'is_active' => true];
    }
}
