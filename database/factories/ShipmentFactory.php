<?php

namespace Database\Factories;

use App\Models\Shipment;
use App\Models\ShippingService;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Shipment> */
class ShipmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reference' => fake()->unique()->bothify('CGO-########'), 'shipping_service_id' => ShippingService::factory(),
            'service_name' => 'Cargo Reguler', 'duration_days' => 14, 'cargo_name' => 'Suku Cadang Motor',
            'vessel_name' => 'Kapal Cargo', 'origin_port' => 'Surabaya', 'destination_port' => 'Tobelo',
            'destination_address' => config('shipping.destination_address'), 'package_count' => 3,
            'departed_on' => Shipment::today()->subDays(5)->toDateString(), 'estimated_arrival_on' => Shipment::today()->addDays(9)->toDateString(),
            'version' => 1,
        ];
    }
}
