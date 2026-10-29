<?php

namespace Tests\Feature;

use App\Models\Distributor;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Shipment;
use App\Models\User;
use App\Services\DistributorInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProcurementIndependenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_has_dedicated_pengadaan_dan_logistik_section(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('dashboard'))->assertOk();

        $response->assertSee('Pengadaan &amp; Logistik', false)
            ->assertSee('Manajemen Produk')
            ->assertSee('Laporan &amp; Analisa', false)
            ->assertSee(route('purchase-orders.index'))
            ->assertSee(route('shipments.index'))
            ->assertSee(route('distributor-invoices.index'))
            ->assertSee(route('distributors.index'));
    }

    public function test_direct_purchase_order_is_standalone_and_does_not_mutate_products_or_stocks(): void
    {
        $distributor = Distributor::factory()->create();
        $user = User::factory()->admin()->create();

        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_stocks', 0);

        $payload = [
            'request_id' => (string) Str::uuid(),
            'distributor_id' => $distributor->id,
            'ordered_on' => '2026-10-29',
            'items' => [
                [
                    'product_name' => 'Spull Assy Racing Bebek',
                    'sku' => 'SPL-RC-01',
                    'unit' => 'pcs',
                    'quantity' => 15,
                    'unit_price' => '120000.00',
                ],
            ],
        ];

        $response = $this->actingAs($user)->post(route('purchase-orders.store'), $payload);
        $response->assertRedirect();

        // Procurement order and item exist
        $this->assertDatabaseCount('purchase_orders', 1);
        $this->assertDatabaseHas('purchase_order_items', [
            'product_name' => 'Spull Assy Racing Bebek',
            'sku' => 'SPL-RC-01',
            'quantity' => 15,
            'product_id' => null,
        ]);

        // Standalone: products and stocks tables remain completely untouched (0 count)
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_stocks', 0);
    }

    public function test_purchase_plan_finalization_with_custom_items_does_not_require_products(): void
    {
        $distributorA = Distributor::factory()->create(['name' => 'Distributor A']);
        $distributorB = Distributor::factory()->create(['name' => 'Distributor B']);
        $user = User::factory()->admin()->create();

        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_stocks', 0);

        $planPayload = [
            'request_id' => (string) Str::uuid(),
            'version' => 0,
            'intent' => 'finalize',
            'name' => 'Rencana Pengadaan Bebas',
            'planned_on' => '2026-10-29',
            'items' => [
                [
                    'product_name' => 'Busi Iridium Racing',
                    'color' => '',
                    'product_kind' => 'daily',
                    'brand' => 'Denso',
                    'part_type' => 'Busi',
                    'current_stock' => 0,
                    'quantity' => 30,
                    'sku' => 'BSI-IR-01',
                    'unit' => 'pcs',
                    'unit_price' => 45000,
                    'allocations' => [
                        ['distributor_id' => $distributorA->id, 'quantity' => 20],
                        ['distributor_id' => $distributorB->id, 'quantity' => 10],
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($user)->postJson(route('purchase-plans.store'), $planPayload);
        $response->assertOk();

        // Orders were generated for both distributors with null product_id
        $this->assertDatabaseCount('purchase_orders', 2);
        $this->assertDatabaseHas('purchase_order_items', [
            'product_name' => 'Busi Iridium Racing',
            'quantity' => 20,
            'product_id' => null,
        ]);
        $this->assertDatabaseHas('purchase_order_items', [
            'product_name' => 'Busi Iridium Racing',
            'quantity' => 10,
            'product_id' => null,
        ]);

        // Standalone: products and stocks remain 0
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_stocks', 0);
    }

    public function test_po_goods_arrival_and_physical_receipt_does_not_automatically_add_or_mutate_product_stocks(): void
    {
        $distributor = Distributor::factory()->create();
        $user = User::factory()->admin()->create();

        // Create a catalogue product with stock = 10
        $catalogueProduct = Product::factory()->withStock(10)->create([
            'name' => 'Kampas Rem Belakang',
            'sku' => 'KMP-REM-01',
        ]);
        $this->assertSame(10, $catalogueProduct->stock->quantity);

        // Create PO
        $order = PurchaseOrder::create([
            'request_id' => (string) Str::uuid(),
            'reference' => 'PO-RCV-TEST-01',
            'distributor_id' => $distributor->id,
            'distributor_name' => $distributor->name,
            'ordered_on' => now()->toDateString(),
        ]);
        $item = $order->items()->create([
            'product_id' => $catalogueProduct->id,
            'product_name' => 'Kampas Rem Belakang',
            'sku' => 'KMP-REM-01',
            'unit' => 'pcs',
            'quantity' => 50,
            'unit_price' => 25000,
        ]);

        // Simulate physical arrival / receipt: actual items arrived at store/warehouse
        $response = $this->actingAs($user)->patch(route('purchase-orders.receive', $order), [
            'version' => 1,
            'items' => [
                [
                    'id' => $item->id,
                    'actual_quantity' => 50,
                ],
            ],
        ]);
        $response->assertRedirect();

        // Verify PO item recorded the actual quantity received
        $item->refresh();
        $this->assertSame(50, $item->actual_quantity);

        // CRITICAL: Verify store product stock was NOT automatically incremented
        $this->assertSame(10, $catalogueProduct->stock->refresh()->quantity);
    }

    public function test_full_pipeline_pengadaan_to_shipping_to_invoice_functions_without_products(): void
    {
        $distributor = Distributor::factory()->create(['name' => 'Distributor Laut Mandiri']);
        $user = User::factory()->admin()->create();

        // 1. Create Purchase Order
        $order = PurchaseOrder::create([
            'request_id' => (string) Str::uuid(),
            'reference' => 'PO-TEST-001',
            'distributor_id' => $distributor->id,
            'distributor_name' => $distributor->name,
            'ordered_on' => now()->toDateString(),
            'notes' => 'Pengadaan mandiri',
        ]);
        $order->items()->create([
            'product_name' => 'Rantai Roda 428',
            'sku' => 'RNT-428',
            'unit' => 'pcs',
            'quantity' => 20,
            'unit_price' => 75000,
            'product_id' => null,
        ]);

        // 2. Create Invoice
        $billing = app(DistributorInvoiceService::class);
        $invoice = $billing->createForOrder($order, now()->addDays(30)->toDateString());
        $invoice->update(['reference' => 'INV-SUP-889']);

        // 3. Create Shipping
        $shipment = Shipment::factory()->create([
            'reference' => 'SHP-SEA-991',
            'cargo_name' => 'Kargo Rantai Roda',
        ]);
        $invoice->update(['shipment_id' => $shipment->id]);

        // Test accessing index pages without errors
        $this->actingAs($user)->get(route('purchase-orders.index'))->assertOk()->assertSee('PO-TEST-001');
        $this->actingAs($user)->get(route('shipments.index'))->assertOk()->assertSee('SHP-SEA-991');
        $this->actingAs($user)->get(route('distributor-invoices.index'))->assertOk()->assertSee('INV-SUP-889');

        // Products table has 0 records and was never touched
        $this->assertDatabaseCount('products', 0);
    }
}
