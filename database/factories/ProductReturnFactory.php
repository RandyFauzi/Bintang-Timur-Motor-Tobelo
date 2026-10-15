<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductReturn;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ProductReturn> */
class ProductReturnFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'request_id' => (string) Str::uuid(),
            'request_hash' => hash('sha256', 'fixture'),
            'product_id' => Product::factory(), 'created_by' => User::factory(),
            'quantity' => 2, 'note_status' => 'pending', 'status' => 'active', 'version' => 1,
            'product_snapshot' => ['name' => fake()->words(3, true), 'sku' => fake()->unique()->bothify('RET-####'), 'kind' => 'daily', 'purchase_price' => '30000.00', 'selling_price' => '50000.00', 'color_id' => null, 'color' => null, 'brand_id' => null, 'brand' => null, 'type_id' => null, 'type' => null, 'distributor_ids' => [], 'distributors' => ''],
        ];
    }
}
