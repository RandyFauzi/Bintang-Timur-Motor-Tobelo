<?php

namespace Database\Factories;

use App\Models\Distributor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PurchaseOrderFactory extends Factory
{
    public function definition(): array
    {
        return ['request_id' => (string) Str::uuid(), 'reference' => 'PO-'.Str::uuid(), 'distributor_id' => Distributor::factory(), 'distributor_name' => fake()->company(), 'ordered_on' => now()->toDateString()];
    }
}
