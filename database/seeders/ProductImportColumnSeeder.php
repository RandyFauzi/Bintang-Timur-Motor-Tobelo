<?php

namespace Database\Seeders;

use App\Models\ProductImportColumn;
use Illuminate\Database\Seeder;

class ProductImportColumnSeeder extends Seeder
{
    public function run(): void
    {
        foreach (ProductImportColumn::DEFAULTS as $field => $definition) {
            ProductImportColumn::firstOrCreate(['field' => $field], $definition);
        }
    }
}
