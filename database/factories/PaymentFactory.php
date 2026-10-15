<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Payment> */
class PaymentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'sale_id' => Sale::factory(),
            'method' => 'cash',
            'amount' => 100000,
            'paid_at' => now(),
        ];
    }
}
