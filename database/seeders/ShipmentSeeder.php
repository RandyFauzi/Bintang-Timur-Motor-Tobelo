<?php

namespace Database\Seeders;

use App\Models\Shipment;
use Illuminate\Database\Seeder;

class ShipmentSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('local') && ! Shipment::exists()) {
            Shipment::factory()->create();
        }
    }
}
