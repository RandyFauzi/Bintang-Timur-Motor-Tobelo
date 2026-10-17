<?php

namespace Tests\Feature;

use App\Models\Shipment;
use App\Models\ShippingService;
use Carbon\CarbonImmutable;
use Database\Seeders\SinarHarapanShipmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SinarHarapanShipmentSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_three_examples_with_distinct_estimated_statuses_without_duplicates(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-17 10:00:00', 'Asia/Jayapura'));
        $this->seed(SinarHarapanShipmentSeeder::class);
        $this->assertDatabaseCount('shipments', 3);
        $this->assertDatabaseCount('shipping_services', 1);
        $this->assertSame(14, ShippingService::sole()->duration_days);
        $shipments = Shipment::orderBy('reference')->get();
        $this->assertSame(['in_transit', 'near_arrival', 'delayed'], $shipments->map(fn (Shipment $shipment): string => $shipment->status())->all());
        foreach ($shipments as $shipment) {
            $this->assertSame('Sinar Harapan', $shipment->service_name);
            $this->assertSame('Tobelo', $shipment->destination_port);
            $this->assertStringContainsString('Gamsungi', $shipment->destination_address);
            $this->assertNull($shipment->delivered_at);
        }
        $first = $shipments->first();
        $first->update(['cargo_name' => 'Diubah oleh pengguna']);
        $this->travel(1)->days();
        $this->seed(SinarHarapanShipmentSeeder::class);
        $this->assertDatabaseCount('shipments', 3);
        $this->assertDatabaseCount('shipping_services', 1);
        $this->assertSame('Diubah oleh pengguna', $first->fresh()->cargo_name);
        $this->assertSame($first->estimated_arrival_on->toDateString(), $first->fresh()->estimated_arrival_on->toDateString());
    }

    public function test_existing_carrier_settings_are_preserved(): void
    {
        $carrier = ShippingService::factory()->create(['name' => 'Sinar Harapan', 'duration_days' => 21, 'is_active' => false]);
        $this->seed(SinarHarapanShipmentSeeder::class);
        $this->assertSame(21, $carrier->fresh()->duration_days);
        $this->assertFalse($carrier->fresh()->is_active);
        $this->assertSame(3, Shipment::where('shipping_service_id', $carrier->id)->count());
    }
}
