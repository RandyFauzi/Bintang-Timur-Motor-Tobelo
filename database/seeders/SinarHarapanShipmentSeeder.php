<?php

namespace Database\Seeders;

use App\Models\Shipment;
use App\Models\ShippingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SinarHarapanShipmentSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $service = ShippingService::firstOrCreate(['name' => 'Sinar Harapan'], ['duration_days' => 14, 'is_active' => true]);
            $today = Shipment::today();
            $examples = [
                ['reference' => 'DEMO-SH-001', 'cargo_name' => 'Oli & Pelumas Motor', 'origin_port' => 'Surabaya', 'package_count' => 8, 'arrival_offset' => 7],
                ['reference' => 'DEMO-SH-002', 'cargo_name' => 'Ban Motor & Aksesoris', 'origin_port' => 'Jakarta', 'package_count' => 5, 'arrival_offset' => 1],
                ['reference' => 'DEMO-SH-003', 'cargo_name' => 'Sparepart Mesin Motor', 'origin_port' => 'Makassar', 'package_count' => 4, 'arrival_offset' => -3],
            ];
            foreach ($examples as $example) {
                $arrival = $today->addDays($example['arrival_offset']);
                Shipment::firstOrCreate(['reference' => $example['reference']], [
                    'shipping_service_id' => $service->id,
                    'service_name' => $service->name,
                    'duration_days' => 14,
                    'cargo_name' => $example['cargo_name'],
                    'origin_port' => $example['origin_port'],
                    'destination_port' => config('shipping.destination_port'),
                    'destination_address' => config('shipping.destination_address'),
                    'package_count' => $example['package_count'],
                    'departed_on' => $arrival->subDays(14)->toDateString(),
                    'estimated_arrival_on' => $arrival->toDateString(),
                    'notes' => 'Data dummy untuk contoh pelacakan cargo Sinar Harapan. Bukan pengiriman nyata.',
                ]);
            }
        });
    }
}
