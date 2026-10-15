<?php

namespace Database\Factories;

use App\Models\Color;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Color> */
class ColorFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'hex_code' => fake()->hexColor(),
            'is_active' => true,
        ];
    }
}
