<?php

namespace Tests\Feature;

use App\Models\Distributor;
use App\Models\Product;
use App\Models\PurchasePlan;
use App\Models\User;
use App\Services\PurchaseOrderNoteService;
use Carbon\CarbonImmutable;
use Database\Seeders\PurchasePlanDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchasePlanDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_builds_one_plan_with_five_distributors_fifteen_products_and_real_workflow_states(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-22 12:00:00', 'Asia/Jayapura'));
        $user = User::factory()->create(['role' => 'admin']);
        $realProduct = Product::factory()->withStock(23)->create(['name' => 'Barang Operasional']);
        $realDistributor = Distributor::factory()->create(['name' => 'Distributor Operasional']);

        $this->seed(PurchasePlanDemoSeeder::class);

        $plan = PurchasePlan::sole();
        $orders = $plan->orders()->with(['items', 'invoice.shipment'])->orderBy('id')->get();
        $this->assertSame('finalized', $plan->status);
        $this->assertCount(15, $plan->items);
        $this->assertSame(75, Product::where('name', 'like', '[DEMO]%')->count());
        foreach (Product::where('name', 'like', '[DEMO]%')->with('distributors')->get() as $product) {
            $this->assertCount(1, $product->distributors);
        }
        $this->assertSame(5, Distributor::where('name', 'like', '[DEMO]%')->count());
        $this->assertCount(5, $orders);
        foreach ($orders as $order) {
            $this->assertCount(15, $order->items);
            foreach ($order->items as $item) {
                $product = Product::findOrFail($item->product_id);
                $this->assertSame([$order->distributor_id], $product->distributors->modelKeys());
                $this->assertEquals($product->purchase_price, $item->unit_price);
            }
        }
        $this->assertDatabaseCount('purchase_order_items', 75);
        $this->assertDatabaseCount('distributor_invoices', 4);
        $this->assertDatabaseCount('shipments', 2);
        $this->assertDatabaseCount('distributor_payments', 1);
        $this->assertFalse($orders[2]->note_issued);
        $this->assertNull($orders[2]->invoice);
        $this->assertNull($orders[0]->shipment_finalized_at);
        $this->assertNull($orders[1]->shipment_finalized_at);
        $this->assertSame(247, $orders[0]->items->sum('invoice_quantity'));
        $this->assertSame(245, $orders[1]->items->sum('invoice_quantity'));
        $this->assertSame('in_transit', $orders[3]->invoice->shipment->status());
        $this->assertSame('delivered', $orders[4]->invoice->shipment->status());
        $this->assertSame('partial', $orders[4]->invoice->status());
        $this->assertSame($orders[4]->invoice->total_cents, $orders[4]->invoice->paid_cents * 2);
        $this->assertNotSame($orders[3]->invoice->shipment_id, $orders[4]->invoice->shipment_id);
        $shortages = app(PurchaseOrderNoteService::class)->shortages($plan->load(['orders.items', 'orders.invoice.shipment', 'orders.invoice.payments']));
        $this->assertSame([12, 2, 2, 2], array_column($shortages, 'quantity'));
        $this->assertSame(23, $realProduct->stock->refresh()->quantity);
        $this->assertSame('Distributor Operasional', $realDistributor->refresh()->name);
        $this->assertDatabaseCount('stock_movements', 0);

        $this->actingAs($user)->get(route('purchase-plans.show', $plan))->assertOk()->assertSee($plan->name);
        $this->get(route('shipments.index', ['shipment' => $orders[3]->invoice->shipment_id]))
            ->assertOk()->assertSee('Pesanan per Distributor')->assertSee($orders[3]->distributor_name);
    }

    public function test_rerunning_demo_does_not_duplicate_records_or_reset_user_edits(): void
    {
        User::factory()->create(['role' => 'admin']);
        $this->seed(PurchasePlanDemoSeeder::class);
        $plan = PurchasePlan::sole();
        $plan->update(['name' => '[DEMO] Rencana yang sedang direview']);
        $product = Product::where('sku', 'DEMO-PO-001')->sole();
        $product->update(['name' => '[DEMO] Nama barang diedit saat review']);

        $this->seed(PurchasePlanDemoSeeder::class);

        $this->assertDatabaseCount('purchase_plans', 1);
        $this->assertDatabaseCount('purchase_orders', 5);
        $this->assertDatabaseCount('products', 75);
        $this->assertDatabaseCount('distributors', 5);
        $this->assertDatabaseCount('distributor_invoices', 4);
        $this->assertDatabaseCount('shipments', 2);
        $this->assertDatabaseCount('distributor_payments', 1);
        $this->assertSame('[DEMO] Rencana yang sedang direview', $plan->refresh()->name);
        $this->assertSame('[DEMO] Nama barang diedit saat review', $product->refresh()->name);
    }
}
