<?php

namespace Database\Factories;

use App\Models\Workshop;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Workshop> */
class WorkshopFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['name' => fake()->unique()->company()];
    }
}
