<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Brand;
use App\Models\ProductType;
use App\Models\Product;
use Illuminate\Support\Str;
use App\Services\InventoryService;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(InventoryService $inventoryService): void
    {
        // 1. Seed Brands
        $brands = [
            ['name' => 'Honda Genuine Parts (AHM)', 'slug' => Str::slug('Honda Genuine Parts AHM')],
            ['name' => 'Yamaha Genuine Parts', 'slug' => Str::slug('Yamaha Genuine Parts')],
            ['name' => 'Federal', 'slug' => Str::slug('Federal')],
            ['name' => 'IRC Tire', 'slug' => Str::slug('IRC Tire')],
            ['name' => 'NGK Spark Plugs', 'slug' => Str::slug('NGK Spark Plugs')],
            ['name' => 'YSS Suspension', 'slug' => Str::slug('YSS Suspension')],
        ];
        
        $brandIds = [];
        foreach ($brands as $b) {
            $brandIds[$b['name']] = Brand::create($b)->id;
        }

        // 2. Seed Types
        $types = [
            ['name' => 'Oli & Pelumas', 'slug' => Str::slug('Oli dan Pelumas')],
            ['name' => 'Ban Motor', 'slug' => Str::slug('Ban Motor')],
            ['name' => 'Sistem Pengapian', 'slug' => Str::slug('Sistem Pengapian')],
            ['name' => 'Sparepart Mesin', 'slug' => Str::slug('Sparepart Mesin')],
            ['name' => 'Aksesoris', 'slug' => Str::slug('Aksesoris')],
        ];

        $typeIds = [];
        foreach ($types as $t) {
            $typeIds[$t['name']] = ProductType::create($t)->id;
        }

        // 3. Seed Products & Stocks
        $products = [
            [
                'name' => 'Oli Federal Matic 30 0.8L',
                'sku' => 'FDR-MTC-08',
                'barcode' => '899123456701',
                'brand_id' => $brandIds['Federal'],
                'type_id' => $typeIds['Oli & Pelumas'],
                'purchase_price' => 35000,
                'selling_price' => 45000,
                'stock_minimum' => 10,
                'initial_stock' => 50,
            ],
            [
                'name' => 'Oli Yamalube Super Matic 1L',
                'sku' => 'YML-SM-10',
                'barcode' => '899123456702',
                'brand_id' => $brandIds['Yamaha Genuine Parts'],
                'type_id' => $typeIds['Oli & Pelumas'],
                'purchase_price' => 55000,
                'selling_price' => 70000,
                'stock_minimum' => 5,
                'initial_stock' => 20,
            ],
            [
                'name' => 'Ban Tubeless IRC Fasti Pro 90/80-14',
                'sku' => 'IRC-FP-908014',
                'barcode' => '899123456703',
                'brand_id' => $brandIds['IRC Tire'],
                'type_id' => $typeIds['Ban Motor'],
                'purchase_price' => 200000,
                'selling_price' => 250000,
                'stock_minimum' => 4,
                'initial_stock' => 12,
            ],
            [
                'name' => 'Busi NGK CPR9EA-9',
                'sku' => 'NGK-CPR9-EA9',
                'barcode' => '899123456704',
                'brand_id' => $brandIds['NGK Spark Plugs'],
                'type_id' => $typeIds['Sistem Pengapian'],
                'purchase_price' => 15000,
                'selling_price' => 22000,
                'stock_minimum' => 15,
                'initial_stock' => 100,
            ],
            [
                'name' => 'Kampas Rem Depan Honda Beat (AHM)',
                'sku' => 'AHM-BRK-F-BT',
                'barcode' => '899123456705',
                'brand_id' => $brandIds['Honda Genuine Parts (AHM)'],
                'type_id' => $typeIds['Sparepart Mesin'],
                'purchase_price' => 40000,
                'selling_price' => 55000,
                'stock_minimum' => 10,
                'initial_stock' => 30,
            ],
            [
                'name' => 'Shockbreaker YSS DTG Vario 125/150',
                'sku' => 'YSS-DTG-V150',
                'barcode' => '899123456706',
                'brand_id' => $brandIds['YSS Suspension'],
                'type_id' => $typeIds['Aksesoris'],
                'purchase_price' => 400000,
                'selling_price' => 480000,
                'stock_minimum' => 2,
                'initial_stock' => 5,
            ],
            [
                'name' => 'V-Belt AHM Vario 150',
                'sku' => 'AHM-VBLT-150',
                'barcode' => '899123456707',
                'brand_id' => $brandIds['Honda Genuine Parts (AHM)'],
                'type_id' => $typeIds['Sparepart Mesin'],
                'purchase_price' => 120000,
                'selling_price' => 145000,
                'stock_minimum' => 5,
                'initial_stock' => 3, // Intentional low stock to trigger alert
            ],
            [
                'name' => 'Oli AHM SPX 2 0.8L',
                'sku' => 'AHM-SPX2-08',
                'barcode' => '899123456708',
                'brand_id' => $brandIds['Honda Genuine Parts (AHM)'],
                'type_id' => $typeIds['Oli & Pelumas'],
                'purchase_price' => 45000,
                'selling_price' => 58000,
                'stock_minimum' => 10,
                'initial_stock' => 0, // Intentional out of stock
            ]
        ];

        DB::transaction(function () use ($products, $inventoryService) {
            foreach ($products as $pData) {
                $initialStock = $pData['initial_stock'];
                unset($pData['initial_stock']);
                
                $product = Product::create($pData);
                
                if ($initialStock > 0) {
                    $inventoryService->adjustStock($product, $initialStock, 'initial', 'Stok Awal dari Sistem Seeder');
                }
            }
        });
    }
}
