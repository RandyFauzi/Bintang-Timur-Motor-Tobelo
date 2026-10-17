<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseOrderItemFactory extends Factory
{
    public function definition(): array
    {
        return ['purchase_order_id' => PurchaseOrder::factory(), 'product_id' => Product::factory(), 'product_name' => fake()->words(3, true), 'sku' => fake()->unique()->bothify('PO-####??'), 'unit' => 'pcs', 'quantity' => 10, 'unit_price' => 25000];
    }
}
