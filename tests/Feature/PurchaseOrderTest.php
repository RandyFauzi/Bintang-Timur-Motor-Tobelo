<?php

namespace Tests\Feature;

use App\Models\Distributor;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use App\Services\DistributorInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class PurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['pending', 'Belum diperiksa'])]
    #[TestWith(['checking', 'Sedang diperiksa'])]
    #[TestWith(['discrepancy', 'Ada selisih'])]
    #[TestWith(['matched', 'Sesuai'])]
    public function test_list_filters_match_receipt_status(string $status, string $label): void
    {
        $orders = [];
        foreach (['pending' => [null, null], 'checking' => [10, null], 'discrepancy' => [10, 0], 'matched' => [10, 10]] as $key => $actuals) {
            $orders[$key] = PurchaseOrder::factory()->create();
            foreach ($actuals as $actual) {
                PurchaseOrderItem::factory()->create(['purchase_order_id' => $orders[$key]->id, 'quantity' => 10, 'actual_quantity' => $actual]);
            }
        }

        $response = $this->actingAs(User::factory()->create())->get(route('purchase-orders.index', ['status' => $status]));

        $response->assertOk()->assertSee($orders[$status]->reference)->assertSee($label);
        foreach ($orders as $key => $order) {
            if ($key !== $status) {
                $response->assertDontSee($order->reference);
            }
        }
    }

    public function test_list_combines_search_distributor_and_inclusive_date_range(): void
    {
        $distributor = Distributor::factory()->create();
        $matching = PurchaseOrder::factory()->create(['reference' => 'PO-FILTER-MATCH', 'distributor_id' => $distributor->id, 'ordered_on' => '2026-10-01']);
        PurchaseOrder::factory()->create(['reference' => 'PO-FILTER-EARLY', 'distributor_id' => $distributor->id, 'ordered_on' => '2026-09-30']);
        PurchaseOrder::factory()->create(['reference' => 'PO-FILTER-OTHER', 'ordered_on' => '2026-10-01']);

        $response = $this->actingAs(User::factory()->create())->get(route('purchase-orders.index', ['search' => 'PO-FILTER', 'distributor_id' => $distributor->id, 'from' => '2026-10-01', 'until' => '2026-10-01']));

        $response->assertOk()->assertSee($matching->reference)->assertDontSee('PO-FILTER-EARLY')->assertDontSee('PO-FILTER-OTHER');
    }

    public function test_list_accepts_end_date_alone_and_preserves_filters_in_pagination(): void
    {
        PurchaseOrder::factory()->count(6)->create(['ordered_on' => '2026-10-01']);
        $later = PurchaseOrder::factory()->create(['ordered_on' => '2026-10-03']);

        $response = $this->actingAs(User::factory()->create())->get(route('purchase-orders.index', ['until' => '2026-10-01', 'per_page' => 5]));

        $response->assertOk()->assertDontSee($later->reference)->assertViewHas('orders', fn ($orders): bool => $orders->total() === 6 && $orders->count() === 5 && str_contains($orders->nextPageUrl(), 'until=2026-10-01'));
    }

    #[TestWith([['status' => 'invalid'], 'status'])]
    #[TestWith([['per_page' => 100000], 'per_page'])]
    #[TestWith([['from' => 'invalid'], 'from'])]
    #[TestWith([['until' => 'invalid'], 'until'])]
    #[TestWith([['from' => '2026-10-02', 'until' => '2026-10-01'], 'until'])]
    public function test_list_rejects_invalid_filters(array $filters, string $field): void
    {
        $this->actingAs(User::factory()->create())->getJson(route('purchase-orders.index', $filters))
            ->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    public function test_guests_cannot_access_orders_or_receipts(): void
    {
        $order = PurchaseOrder::factory()->create();
        foreach (['purchase-orders.index', 'purchase-orders.create'] as $route) {
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
        $payload = ['request_id' => (string) Str::uuid(), 'distributor_id' => $distributor->id, 'ordered_on' => '2026-10-17', 'items' => [['product_name' => 'Kampas Rem', 'unit' => 'pcs', 'quantity' => 10, 'unit_price' => '12500.50']]];
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

    public function test_invalid_manual_items_and_inactive_distributor_are_rejected(): void
    {
        $distributor = Distributor::factory()->create(['is_active' => false]);
        $row = ['product_name' => '', 'unit' => '', 'quantity' => 0, 'unit_price' => -1];
        $this->actingAs(User::factory()->create())->postJson(route('purchase-orders.store'), ['request_id' => (string) Str::uuid(), 'distributor_id' => $distributor->id, 'ordered_on' => '2026-10-17', 'items' => [$row, $row]])->assertUnprocessable()->assertJsonValidationErrors(['distributor_id', 'items.0.product_name', 'items.0.unit', 'items.0.quantity', 'items.0.unit_price']);
        $this->assertDatabaseCount('purchase_orders', 0);
    }

    public function test_distributor_registration_returns_to_manual_order_form(): void
    {
        $this->actingAs(User::factory()->create())->post(route('purchase-orders.distributors.store'), ['name' => 'Pemasok Baru'])->assertSessionHasNoErrors()->assertRedirect();
        $distributor = Distributor::sole();
        $this->get(route('purchase-orders.create', ['distributor_id' => $distributor->id]))->assertOk()->assertSee('Pemasok Baru');

    }

    public function test_reused_order_token_with_different_items_or_payment_term_is_rejected(): void
    {
        $payload = ['request_id' => (string) Str::uuid(), 'distributor_id' => Distributor::factory()->create()->id, 'ordered_on' => '2026-10-17', 'items' => [['product_name' => 'Kampas Rem', 'unit' => 'pcs', 'quantity' => 10, 'unit_price' => '10000']]];
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
        $this->actingAs(User::factory()->create())->withSession(['_old_input' => ['items' => [['product_name' => ['bad'], 'quantity' => ['bad']]], 'distributor_id' => ['bad'], 'request_id' => ['bad']]])->get(route('purchase-orders.create'))->assertOk();
    }

    public function test_manual_po_works_without_products_and_allows_repeated_supplier_names(): void
    {
        $row = ['product_name' => 'Nama Nota Distributor', 'sku' => 'KODE-SAMA', 'unit' => 'dus', 'quantity' => 2, 'unit_price' => '15000'];
        $this->actingAs(User::factory()->create())->post(route('purchase-orders.store'), [
            'request_id' => (string) Str::uuid(), 'distributor_id' => Distributor::factory()->create()->id,
            'ordered_on' => '2026-10-17', 'items' => [$row, $row],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_stocks', 0);
        $this->assertDatabaseCount('purchase_order_items', 2);
        $this->assertDatabaseHas('distributor_invoices', ['total_cents' => 6000000]);
        $order = PurchaseOrder::sole();
        $this->get(route('purchase-orders.show', $order))->assertSee('Nama Nota Distributor')->assertSee('KODE-SAMA');
    }

    public function test_detaching_existing_items_preserves_names_receipts_and_invoices(): void
    {
        $migration = require database_path('migrations/2026_10_17_085657_detach_purchase_order_items_from_products.php');
        $migration->down();
        $product = Product::factory()->withStock(7)->create();
        $item = PurchaseOrderItem::factory()->create(['product_name' => 'Nama PO Lama', 'actual_quantity' => 5]);
        DB::table('purchase_order_items')->where('id', $item->id)->update(['product_id' => $product->id]);
        $invoice = app(DistributorInvoiceService::class)->createForOrder(PurchaseOrder::findOrFail($item->purchase_order_id));
        $migration->up();
        $product->forceDelete();
        $this->assertDatabaseHas('purchase_order_items', ['id' => $item->id, 'product_name' => 'Nama PO Lama', 'actual_quantity' => 5]);
        $this->assertModelExists($invoice);
        $this->actingAs(User::factory()->create())->get(route('purchase-orders.show', $item->purchase_order_id))->assertOk()->assertSee('Nama PO Lama')->assertSee('Kurang 5');
    }

    public function test_order_search_filters_distributor_and_escapes_notes(): void
    {
        $first = PurchaseOrder::factory()->create(['distributor_name' => 'Pemasok Utama', 'notes' => '<script>alert(1)</script>']);
        PurchaseOrder::factory()->create(['distributor_name' => 'Pemasok Lain']);
        $this->actingAs(User::factory()->create())->get(route('purchase-orders.index', ['search' => 'Utama']))->assertSee('Pemasok Utama')->assertDontSee('Pemasok Lain');
        $this->get(route('purchase-orders.show', $first))->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }
}
