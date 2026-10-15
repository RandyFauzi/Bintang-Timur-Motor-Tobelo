<?php

namespace Database\Factories;

use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Sale> */
class SaleFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'invoice_number' => fake()->unique()->bothify('INV-########-????'),
            'user_id' => User::factory(),
            'subtotal' => 100000,
            'grand_total' => 100000,
            'paid_amount' => 100000,
            'payment_status' => 'paid',
            'status' => 'completed',
            'sold_at' => now(),
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => ['status' => 'cancelled']);
    }
}
