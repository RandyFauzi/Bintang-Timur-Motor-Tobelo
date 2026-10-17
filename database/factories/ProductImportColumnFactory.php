<?php

namespace Database\Factories;

use App\Models\ProductImportColumn;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProductImportColumn> */
class ProductImportColumnFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['field' => 'name', 'label' => 'Nama Produk', 'aliases' => ['nama barang', 'product name']];
    }
}
