<?php

namespace Database\Seeders;

use App\Models\ShippingService;
use Illuminate\Database\Seeder;

class ShippingServiceSeeder extends Seeder
{
    public function run(): void
    {
        ShippingService::firstOrCreate(['name' => 'Cargo Reguler'], ['duration_days' => 14, 'is_active' => true]);
    }
}
