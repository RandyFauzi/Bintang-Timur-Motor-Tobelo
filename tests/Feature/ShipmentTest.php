<?php

namespace Tests\Feature;

use App\Models\Shipment;
use App\Models\ShippingService;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ShipmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-17 00:30:00', 'Asia/Jayapura'));
    }

    public function test_guests_cannot_access_shipping_pages_or_actions(): void
    {
        $shipment = Shipment::factory()->create();
        $carrier = ShippingService::factory()->create();
        $this->get(route('shipments.index'))->assertRedirect(route('login'));
        $this->get(route('shipments.notifications'))->assertRedirect(route('login'));
        $this->post(route('shipments.store'))->assertRedirect(route('login'));
        $this->patch(route('shipments.update', $shipment))->assertRedirect(route('login'));
        $this->patch(route('shipments.arrive', $shipment))->assertRedirect(route('login'));
        $this->post(route('shipping-services.store'))->assertRedirect(route('login'));
        $this->patch(route('shipping-services.update', $carrier))->assertRedirect(route('login'));
    }

    public function test_carrier_duration_accepts_weeks_and_shipments_snapshot_the_estimate(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('shipping-services.store'), ['name' => 'WMS Cargo', 'duration' => 2, 'duration_unit' => 'weeks', 'is_active' => 1])
            ->assertRedirect()->assertSessionHasNoErrors();
        $carrier = ShippingService::sole();
        $this->assertSame(14, $carrier->duration_days);
        $this->post(route('shipments.store'), $this->payload($carrier) + ['estimated_arrival_on' => '2026-10-18', 'received_by' => $user->id, 'delivered_at' => now()])
            ->assertRedirect()->assertSessionHasNoErrors();
        $shipment = Shipment::sole();
        $this->assertSame('2026-10-31', $shipment->estimated_arrival_on->toDateString());
        $this->assertNull($shipment->delivered_at);
        $this->assertNull($shipment->received_by);
        $this->assertSame($user->id, $shipment->created_by);
        $this->assertStringStartsWith('CGO-', $shipment->reference);
        $this->patch(route('shipping-services.update', $carrier), ['name' => 'Nama Baru', 'duration' => 21, 'duration_unit' => 'days', 'is_active' => 1])->assertSessionHasNoErrors();
        $this->assertSame(14, $shipment->fresh()->duration_days);
        $this->assertSame('WMS Cargo', $shipment->fresh()->service_name);
        $this->patch(route('shipments.update', $shipment), $this->payload($carrier) + ['version' => $shipment->version])->assertSessionHasNoErrors();
        $this->assertSame('2026-10-31', $shipment->fresh()->estimated_arrival_on->toDateString());
    }

    #[DataProvider('statusCases')]
    public function test_status_boundaries_follow_tobelo_dates_and_filters_match(string $departure, string $arrival, ?string $delivered, string $expected): void
    {
        $shipment = Shipment::factory()->create(['departed_on' => $departure, 'estimated_arrival_on' => $arrival, 'delivered_at' => $delivered]);
        $this->assertSame($expected, $shipment->status());
        $this->assertSame($shipment->id, Shipment::withStatus($expected)->sole()->id);
        $this->assertSame(0, Shipment::whereKey($shipment->id)->whereNotNull('delivered_at')->count(), $delivered ? '' : 'No automatic receipt');
    }

    public static function statusCases(): array
    {
        return [
            'planned' => ['2026-10-18', '2026-11-01', null, 'scheduled'],
            'transit' => ['2026-10-06', '2026-10-20', null, 'in_transit'],
            'two days away' => ['2026-10-05', '2026-10-19', null, 'near_arrival'],
            'due today' => ['2026-10-03', '2026-10-17', null, 'near_arrival'],
            'overdue' => ['2026-10-02', '2026-10-16', null, 'delayed'],
        ];
    }

    public function test_arrival_requires_user_confirmation_is_idempotent_and_removes_alerts(): void
    {
        $user = User::factory()->create();
        $shipment = Shipment::factory()->create(['estimated_arrival_on' => '2026-10-16']);
        $this->actingAs($user)->getJson(route('shipments.notifications', ['ids' => [$shipment->id]]))->assertOk()
            ->assertJsonPath('count', 1)->assertJsonPath('states.'.$shipment->id.'.status', 'delayed');
        $this->patch(route('shipments.arrive', $shipment), ['version' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $shipment->refresh();
        $this->assertSame('delivered', $shipment->status());
        $this->assertSame($user->id, $shipment->received_by);
        $confirmed = $shipment->delivered_at->toISOString();
        $this->patch(route('shipments.arrive', $shipment), ['version' => 1])->assertSessionHasNoErrors();
        $this->assertSame($confirmed, $shipment->fresh()->delivered_at->toISOString());
        $this->assertSame(2, $shipment->fresh()->version);
        $this->getJson(route('shipments.notifications'))->assertOk()->assertJsonPath('count', 0);
        $this->assertDatabaseHas('activity_logs', ['route_name' => 'shipments.arrive', 'result' => 'success']);
    }

    public function test_stale_or_scheduled_shipments_cannot_be_confirmed_and_delivered_shipments_cannot_be_edited(): void
    {
        $this->actingAs(User::factory()->create());
        $shipment = Shipment::factory()->create();
        $this->patchJson(route('shipments.arrive', $shipment), ['version' => 99])->assertUnprocessable();
        $future = Shipment::factory()->create(['departed_on' => '2026-10-18']);
        $this->patchJson(route('shipments.arrive', $future), ['version' => 1])->assertUnprocessable();
        $this->assertNull($future->fresh()->delivered_at);
        $this->patch(route('shipments.arrive', $shipment), ['version' => 1])->assertSessionHasNoErrors();
        $this->patchJson(route('shipments.update', $shipment), $this->payload($shipment->shippingService) + ['version' => 2])->assertUnprocessable();
    }

    public function test_edit_recalculates_departure_using_snapshot_and_rejects_stale_versions(): void
    {
        $this->actingAs(User::factory()->create());
        $carrier = ShippingService::factory()->create();
        $shipment = Shipment::factory()->create(['shipping_service_id' => $carrier->id]);
        $payload = $this->payload($carrier);
        $payload['departed_on'] = '2026-10-18';
        $this->patch(route('shipments.update', $shipment), $payload + ['version' => 1])->assertSessionHasNoErrors();
        $this->assertSame('2026-11-01', $shipment->fresh()->estimated_arrival_on->toDateString());
        $this->patchJson(route('shipments.update', $shipment), $payload + ['version' => 1])->assertUnprocessable();
        $this->assertSame(2, $shipment->fresh()->version);
    }

    public function test_inactive_service_cannot_be_used_for_new_shipments_but_existing_cargo_can_be_edited(): void
    {
        $this->actingAs(User::factory()->create());
        $carrier = ShippingService::factory()->create(['is_active' => false]);
        $shipment = Shipment::factory()->create(['shipping_service_id' => $carrier->id]);
        $this->postJson(route('shipments.store'), $this->payload($carrier))->assertUnprocessable()->assertJsonValidationErrors('shipping_service_id');
        $this->patch(route('shipments.update', $shipment), $this->payload($carrier) + ['version' => 1])->assertSessionHasNoErrors();
        $this->assertFalse($carrier->fresh()->is_active);
    }

    public function test_notifications_only_include_unconfirmed_near_or_delayed_shipments(): void
    {
        $near = Shipment::factory()->create(['cargo_name' => 'Cargo Mendekat', 'estimated_arrival_on' => '2026-10-19']);
        Shipment::factory()->create(['estimated_arrival_on' => '2026-10-16']);
        Shipment::factory()->create(['estimated_arrival_on' => '2026-10-20']);
        Shipment::factory()->create(['estimated_arrival_on' => '2026-10-16', 'delivered_at' => now()]);
        $this->actingAs(User::factory()->create())->getJson(route('shipments.notifications'))->assertOk()->assertJsonPath('count', 2)->assertJsonCount(2, 'alerts')->assertJsonPath('alerts.1.url', route('shipments.index', ['shipment' => $near->id]));
        $this->get(route('products.index'))->assertOk()->assertSee('Cargo Mendekat')->assertSee('Pelacak Pengiriman');
    }

    public function test_tracking_page_renders_safe_details_default_address_filters_and_empty_state(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get(route('shipments.index'))->assertOk()->assertSee('Perjalanan cargo dimulai di sini')->assertSee('Gamsungi');
        $shipment = Shipment::factory()->create(['cargo_name' => '<script>cargo</script>', 'estimated_arrival_on' => '2026-10-16']);
        $this->get(route('shipments.index', ['shipment' => $shipment->id]))->assertOk()->assertSee('&lt;script&gt;cargo&lt;/script&gt;', false)->assertDontSee('<script>cargo</script>', false)->assertSee('Konfirmasi Sudah Sampai');
        $response = $this->get(route('shipments.index', ['search' => 'cargo', 'status' => 'delayed']));
        $this->assertSame(1, $response->assertOk()->viewData('shipments')->total());
        $this->get(route('shipments.index', ['edit' => $shipment->id]))->assertOk()->assertSee('Edit Pengiriman');
        $this->get(route('shipments.index', ['service_edit' => $shipment->shipping_service_id]))->assertOk()->assertSee('Edit Jasa Pengiriman');
    }

    public function test_invalid_input_is_rejected_without_partial_records(): void
    {
        $this->actingAs(User::factory()->create());
        $carrier = ShippingService::factory()->create();
        $payload = $this->payload($carrier);
        $payload['departed_on'] = 'tanggal-salah';
        $payload['package_count'] = 0;
        $this->postJson(route('shipments.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors(['departed_on', 'package_count']);
        $this->postJson(route('shipping-services.store'), ['name' => $carrier->name, 'duration' => 0, 'duration_unit' => 'wrong', 'is_active' => 1])->assertUnprocessable()->assertJsonValidationErrors(['name', 'duration', 'duration_unit']);
        $this->assertDatabaseCount('shipments', 0);
    }

    /** @return array<string, mixed> */
    private function payload(ShippingService $carrier): array
    {
        return ['shipping_service_id' => $carrier->id, 'cargo_name' => 'Cargo Oli', 'origin_port' => 'Surabaya', 'destination_port' => 'Tobelo', 'destination_address' => config('shipping.destination_address'), 'package_count' => 2, 'departed_on' => '2026-10-17'];
    }
}
