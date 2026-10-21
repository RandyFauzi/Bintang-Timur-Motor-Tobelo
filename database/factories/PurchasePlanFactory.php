<?php

namespace Database\Factories;

use App\Models\PurchasePlan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PurchasePlan>
 */
class PurchasePlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'request_id' => (string) Str::uuid(),
            'reference' => 'PGD-'.fake()->unique()->bothify('########-????'),
            'name' => 'Pengadaan '.fake()->words(2, true),
            'planned_on' => now()->toDateString(),
            'items' => [],
            'status' => 'draft',
        ];
    }
}
