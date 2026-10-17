<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Shipment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-17 08:00:00', 'Asia/Jayapura'));
    }

    public function test_guests_cannot_open_notification_center(): void
    {
        $this->get(route('notifications.index'))->assertRedirect(route('login'));
    }

    public function test_all_active_conditions_are_combined_and_urgent_alerts_appear_first(): void
    {
        Product::factory()->withStock(0)->create(['name' => 'Stok Kosong']);
        Product::factory()->create(['name' => 'Belum Ada Stok']);
        Product::factory()->withStock(3)->create(['name' => 'Stok Tipis', 'stock_minimum' => 5]);
        Product::factory()->withStock(20)->create(['name' => 'Stok Aman']);
        Product::factory()->inactive()->withStock(0)->create();
        $deleted = Product::factory()->withStock(0)->create();
        $deleted->delete();
        $late = Shipment::factory()->create(['cargo_name' => 'Cargo Terlambat', 'estimated_arrival_on' => '2026-10-16']);
        Shipment::factory()->create(['cargo_name' => 'Cargo Mendekat', 'estimated_arrival_on' => '2026-10-19']);
        Shipment::factory()->create(['cargo_name' => 'Cargo Normal', 'estimated_arrival_on' => '2026-10-21']);
        Shipment::factory()->create(['departed_on' => '2026-10-18', 'estimated_arrival_on' => '2026-10-19']);
        Shipment::factory()->create(['estimated_arrival_on' => '2026-10-16', 'delivered_at' => now()]);
        $response = $this->actingAs(User::factory()->create())->get(route('notifications.index'))->assertOk();
        $this->assertSame(5, $response->viewData('total'));
        $this->assertSame(['out_of_stock' => 2, 'shipment_delayed' => 1, 'low_stock' => 1, 'shipment_near' => 1], $response->viewData('counts'));
        $this->assertSame('shipment_delayed', $response->viewData('notifications')->first()->category);
        $response->assertSee('Stok Kosong')->assertSee('Belum Ada Stok')->assertSee('Stok Tipis')->assertSee('Cargo Mendekat')
            ->assertSee(route('shipments.index', ['shipment' => $late->id]).'#shipment-detail', false);
    }

    public function test_all_alerts_are_paginated_beyond_sidebar_limit_and_page_size_works(): void
    {
        Product::factory()->count(22)->withStock(0)->create();
        Shipment::factory()->count(7)->create(['estimated_arrival_on' => '2026-10-16']);
        $this->actingAs(User::factory()->create());
        $first = $this->get(route('notifications.index'))->assertOk()->viewData('notifications');
        $second = $this->get(route('notifications.index', ['page' => 2]))->assertOk()->viewData('notifications');
        $this->assertSame(29, $first->total());
        $this->assertCount(15, $first->items());
        $this->assertCount(14, $second->items());
        $keys = fn (LengthAwarePaginator $page): array => array_map(fn (object $row): string => $row->source.':'.$row->source_id, $page->items());
        $this->assertSame([], array_values(array_intersect($keys($first), $keys($second))));
        $this->assertCount(5, $this->get(route('notifications.index', ['per_page' => 5]))->assertOk()->viewData('notifications')->items());
    }

    public function test_search_and_type_filter_keep_overall_counts_and_link_to_the_correct_record(): void
    {
        $product = Product::factory()->withStock(2)->create(['name' => 'Oli Tipis', 'sku' => 'CARI-OLI']);
        Shipment::factory()->create(['cargo_name' => 'Cargo Ban', 'service_name' => 'Sinar Harapan', 'reference' => 'RESI-123', 'estimated_arrival_on' => '2026-10-16']);
        $this->actingAs(User::factory()->create());
        foreach (['Sinar Harapan', 'RESI-123', 'Surabaya', 'Tobelo'] as $search) {
            $response = $this->get(route('notifications.index', ['type' => 'shipment_delayed', 'search' => $search]))->assertOk();
            $this->assertSame(1, $response->viewData('notifications')->total());
            $this->assertSame(2, $response->viewData('total'));
        }
        $response = $this->get(route('notifications.index', ['search' => 'CARI-OLI']))->assertOk()->assertSee('Oli Tipis')
            ->assertSee(route('products.index', ['edit' => $product->id]), false);
        $this->assertSame(1, $response->viewData('notifications')->total());
    }

    public function test_resolved_conditions_disappear_without_deleting_the_underlying_records(): void
    {
        $product = Product::factory()->withStock(0)->create();
        $shipment = Shipment::factory()->create(['estimated_arrival_on' => '2026-10-16']);
        $this->actingAs(User::factory()->create());
        $this->assertSame(2, $this->get(route('notifications.index'))->viewData('total'));
        $product->stock->update(['quantity' => 20]);
        $this->patch(route('shipments.arrive', $shipment), ['version' => 1])->assertSessionHasNoErrors();
        $response = $this->get(route('notifications.index'))->assertOk()->assertSee('Tidak ada notifikasi');
        $this->assertSame(0, $response->viewData('total'));
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('shipments', 1);
    }

    public function test_invalid_filters_are_rejected_and_user_content_is_escaped(): void
    {
        $this->actingAs(User::factory()->create());
        $this->getJson(route('notifications.index', ['type' => 'wrong', 'page' => -1, 'per_page' => 999]))
            ->assertUnprocessable()->assertJsonValidationErrors(['type', 'page', 'per_page']);
        Product::factory()->withStock(0)->create(['name' => '<script>alert(1)</script>']);
        $this->get(route('notifications.index'))->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_badge_counts_all_alerts_and_refreshes_after_stock_and_arrival_changes(): void
    {
        $products = Product::factory()->count(7)->withStock(0)->create();
        $shipments = Shipment::factory()->count(2)->create(['estimated_arrival_on' => '2026-10-16']);
        $this->actingAs(User::factory()->create());
        $this->get(route('dashboard'))->assertOk()->assertSee('data-notification-badge', false)->assertSee('aria-live="polite">9</span>', false);
        $this->getJson(route('shipments.notifications'))->assertOk()->assertJsonPath('stock_count', 7)->assertJsonPath('active_count', 9)->assertJsonCount(5, 'stock_alerts');
        $products->first()->stock->update(['quantity' => 20]);
        $this->patch(route('shipments.arrive', $shipments->first()), ['version' => 1])->assertSessionHasNoErrors();
        $this->getJson(route('shipments.notifications'))->assertOk()->assertJsonPath('stock_count', 6)->assertJsonPath('active_count', 7);
        foreach ($products as $product) {
            $product->stock->update(['quantity' => 20]);
        }
        $this->patch(route('shipments.arrive', $shipments->last()), ['version' => 1])->assertSessionHasNoErrors();
        $this->getJson(route('shipments.notifications'))->assertOk()->assertJsonPath('active_count', 0)->assertJsonCount(0, 'stock_alerts');
        $products->first()->stock->update(['quantity' => 5]);
        $this->getJson(route('shipments.notifications'))->assertOk()->assertJsonPath('active_count', 1);
        $this->assertSame('low_stock', $this->get(route('notifications.index'))->viewData('notifications')->first()->category);
        $products->first()->stock->update(['quantity' => 6]);
        $this->getJson(route('shipments.notifications'))->assertOk()->assertJsonPath('active_count', 0);
        $products->first()->stock->update(['quantity' => 0]);
        $this->getJson(route('shipments.notifications'))->assertOk()->assertJsonPath('active_count', 1);
    }

    public function test_notification_pagination_remains_visible_and_footer_note_is_removed(): void
    {
        Product::factory()->withStock(0)->create();
        $this->actingAs(User::factory()->create())->get(route('notifications.index'))->assertOk()
            ->assertSee('aria-label="Halaman data"', false)->assertSee('aria-disabled="true"', false)
            ->assertDontSee('Halaman ini menampilkan kondisi terkini.');
    }

    public function test_automatic_refresh_removes_resolved_items_and_preserves_filters(): void
    {
        $product = Product::factory()->withStock(0)->create(['name' => 'Barang Perlu Diisi']);
        $this->actingAs(User::factory()->create());
        $url = route('notifications.index', ['type' => 'out_of_stock']);
        $response = $this->getJson($url)->assertOk()->assertJsonPath('total', 1);
        $this->assertStringContainsString('Barang Perlu Diisi', $response->json('html'));
        $this->assertStringNotContainsString('Perbarui Notifikasi', $response->json('html'));
        $product->stock->update(['quantity' => 20]);
        $response = $this->getJson($url)->assertOk()->assertJsonPath('total', 0)->assertJsonPath('counts.out_of_stock', 0);
        $this->assertStringNotContainsString('Barang Perlu Diisi', $response->json('html'));
        $this->assertStringContainsString('Tidak ada notifikasi', $response->json('html'));
        $this->get($url)->assertOk()->assertDontSee('Perbarui Notifikasi')->assertSee('notification-metric__mark', false);
    }

    public function test_automatic_refresh_returns_a_valid_page_when_resolved_items_remove_the_current_page(): void
    {
        $this->actingAs(User::factory()->create());
        Product::factory()->withStock(0)->create();
        $response = $this->getJson(route('notifications.index', ['type' => 'out_of_stock', 'page' => 2]))->assertOk();
        $this->assertStringContainsString('type=out_of_stock', $response->json('page_url'));
        $this->assertStringContainsString('page=1', $response->json('page_url'));
    }

    public function test_sidebar_provides_access_even_when_there_are_no_alerts(): void
    {
        $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertOk()
            ->assertSee('Lihat Semua Notifikasi')->assertSee(route('notifications.index'), false);
    }
}
