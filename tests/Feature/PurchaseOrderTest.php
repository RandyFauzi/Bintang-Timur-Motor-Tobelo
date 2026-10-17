<?php

namespace Tests\Feature;

use App\Models\Distributor;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class PurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_orders_or_receipts(): void
    {
        $order = PurchaseOrder::factory()->create();
        foreach (['purchase-orders.index', 'purchase-orders.create', 'purchase-orders.products'] as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }
        $this->get(route('purchase-orders.show', $order))->assertRedirect(route('login'));
        $this->post(route('purchase-orders.store'))->assertRedirect(route('login'));
        $this->post(route('purchase-orders.distributors.store'))->assertRedirect(route('login'));
        $this->patch(route('purchase-orders.receive', $order))->assertRedirect(route('login'));
    }

    public function test_order_creation_preserves_prices_and_names_and_does_not_change_stock(): void
    {
        $distributor = Distributor::factory()->create(['name' => 'Distributor Tobelo']);
        $product = Product::factory()->withStock(7)->create(['name' => 'Kampas Rem']);
        $payload = ['request_id' => (string) Str::uuid(), 'distributor_id' => $distributor->id, 'ordered_on' => '2026-10-17', 'items' => [['product_id' => $product->id, 'quantity' => 10, 'unit_price' => '12500.50']]];
        $this->actingAs(User::factory()->create())->post(route('purchase-orders.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $order = PurchaseOrder::with('items')->sole();
        $this->assertSame(125005.0, $order->total());
        $this->assertDatabaseHas('purchase_order_items', ['product_name' => 'Kampas Rem', 'quantity' => 10, 'actual_quantity' => null]);
        $this->assertSame(7, $product->stock->quantity);
        $product->update(['name' => 'Nama baru']);
        $this->get(route('purchase-orders.show', $order))->assertOk()->assertSee('Kampas Rem')->assertSee('Belum diperiksa');
        $this->get(route('purchase-orders.index'))->assertOk()->assertSee('Distributor Tobelo')->assertSee('125.005,00');
        $this->post(route('purchase-orders.store'), $payload)->assertRedirect();
        $this->assertDatabaseCount('purchase_orders', 1);
    }

    #[TestWith([null, 'Belum diperiksa'])]
    #[TestWith([0, 'Tidak datang / Kosong'])]
    #[TestWith([5, 'Kurang 5'])]
    #[TestWith([20, 'Lebih 10'])]
    #[TestWith([10, 'Sesuai'])]
    public function test_actual_quantity_is_saved_and_discrepancy_is_displayed(?int $actual, string $label): void
    {
        $item = PurchaseOrderItem::factory()->create();
        $this->actingAs(User::factory()->create())->patch(route('purchase-orders.receive', $item->purchase_order_id), ['version' => 1, 'items' => [['id' => $item->id, 'actual_quantity' => $actual]]])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame($actual, $item->refresh()->actual_quantity);
        $this->get(route('purchase-orders.show', $item->purchase_order_id))->assertOk()->assertSee($label);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_other_order_items_and_negative_actuals_are_rejected_without_changes(): void
    {
        $item = PurchaseOrderItem::factory()->create();
        $other = PurchaseOrderItem::factory()->create();
        $this->actingAs(User::factory()->create())->patchJson(route('purchase-orders.receive', $item->purchase_order_id), ['version' => 1, 'items' => [['id' => $other->id, 'actual_quantity' => -1]]])->assertUnprocessable()->assertJsonValidationErrors(['items.0.id', 'items.0.actual_quantity']);
        $this->assertSame(null, $other->refresh()->actual_quantity);
        $this->assertSame(null, $item->refresh()->actual_quantity);
    }

    public function test_stale_receipts_do_not_overwrite_and_partial_checking_has_its_own_status(): void
    {
        $order = PurchaseOrder::factory()->create();
        $item = PurchaseOrderItem::factory()->create(['purchase_order_id' => $order->id]);
        PurchaseOrderItem::factory()->create(['purchase_order_id' => $order->id]);
        $this->actingAs(User::factory()->create())->patch(route('purchase-orders.receive', $order), ['version' => 1, 'items' => [['id' => $item->id, 'actual_quantity' => 5]]])->assertSessionHasNoErrors();
        $this->get(route('purchase-orders.show', $order))->assertSee('Sedang diperiksa');
        $this->patchJson(route('purchase-orders.receive', $order), ['version' => 1, 'items' => [['id' => $item->id, 'actual_quantity' => 10]]])->assertUnprocessable()->assertJsonValidationErrors('version');
        $this->assertSame(5, $item->refresh()->actual_quantity);
    }

    public function test_duplicate_products_and_inactive_catalogue_entries_are_rejected(): void
    {
        $distributor = Distributor::factory()->create(['is_active' => false]);
        $product = Product::factory()->create(['is_active' => false]);
        $row = ['product_id' => $product->id, 'quantity' => 0, 'unit_price' => -1];
        $this->actingAs(User::factory()->create())->postJson(route('purchase-orders.store'), ['request_id' => (string) Str::uuid(), 'distributor_id' => $distributor->id, 'ordered_on' => '2026-10-17', 'items' => [$row, $row]])->assertUnprocessable()->assertJsonValidationErrors(['distributor_id', 'items.0.product_id', 'items.0.quantity', 'items.0.unit_price']);
        $this->assertDatabaseCount('purchase_orders', 0);
    }

    public function test_distributor_registration_returns_to_order_form_and_product_search_is_active_only(): void
    {
        $this->actingAs(User::factory()->create())->post(route('purchase-orders.distributors.store'), ['name' => 'Pemasok Baru'])->assertSessionHasNoErrors()->assertRedirect();
        $distributor = Distributor::sole();
        $this->get(route('purchase-orders.create', ['distributor_id' => $distributor->id]))->assertOk()->assertSee('Pemasok Baru');
        Product::factory()->create(['name' => 'Produk Aktif']);
        Product::factory()->create(['name' => 'Produk Arsip', 'is_active' => false]);
        $this->getJson(route('purchase-orders.products', ['search' => 'Produk']))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Produk Aktif');
    }

    public function test_reused_order_token_with_different_items_or_payment_term_is_rejected(): void
    {
        $product = Product::factory()->create();
        $payload = ['request_id' => (string) Str::uuid(), 'distributor_id' => Distributor::factory()->create()->id, 'ordered_on' => '2026-10-17', 'items' => [['product_id' => $product->id, 'quantity' => 10, 'unit_price' => '10000']]];
        $this->actingAs(User::factory()->create())->post(route('purchase-orders.store'), $payload)->assertSessionHasNoErrors();
        $payload['payment_term_months'] = 2;
        $this->postJson(route('purchase-orders.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('request_id');
        unset($payload['payment_term_months']);
        $payload['items'][0]['quantity'] = 20;
        $this->postJson(route('purchase-orders.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('request_id');
        $this->assertDatabaseCount('purchase_orders', 1);
        $this->assertDatabaseHas('purchase_order_items', ['quantity' => 10]);
    }

    public function test_invalid_form_payload_can_be_rendered_after_validation_failure(): void
    {
        $this->actingAs(User::factory()->create())->withSession(['_old_input' => ['items' => [['product_id' => ['bad'], 'quantity' => ['bad']]], 'distributor_id' => ['bad'], 'request_id' => ['bad']]])->get(route('purchase-orders.create'))->assertOk();
    }

    public function test_order_search_filters_distributor_and_escapes_notes(): void
    {
        $first = PurchaseOrder::factory()->create(['distributor_name' => 'Pemasok Utama', 'notes' => '<script>alert(1)</script>']);
        PurchaseOrder::factory()->create(['distributor_name' => 'Pemasok Lain']);
        $this->actingAs(User::factory()->create())->get(route('purchase-orders.index', ['search' => 'Utama']))->assertSee('Pemasok Utama')->assertDontSee('Pemasok Lain');
        $this->get(route('purchase-orders.show', $first))->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }
}
