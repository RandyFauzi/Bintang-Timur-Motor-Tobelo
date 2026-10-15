<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'sku' => fake()->unique()->bothify('PRD-########'),
            'purchase_price' => 25000,
            'selling_price' => 50000,
            'stock_minimum' => 5,
            'is_active' => true,
        ];
    }

    public function withStock(int $quantity = 10): static
    {
        return $this->afterCreating(function (Product $product) use ($quantity): void {
            $product->stock()->create(['quantity' => $quantity, 'reserved_quantity' => 0]);
        });
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
