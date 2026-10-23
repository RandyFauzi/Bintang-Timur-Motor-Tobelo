<?php

namespace Tests\Feature;

use App\Models\Distributor;
use App\Models\DistributorInvoice;
use App\Models\DistributorPayment;
use App\Models\Product;
use App\Models\PurchaseOrderItem;
use App\Models\PurchasePlan;
use App\Models\SaleItem;
use App\Models\User;
use App\Services\PurchaseOrderNoteService;
use Carbon\CarbonImmutable;
use Database\Seeders\PurchasePlanDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class PurchasePlanDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_rebuilds_only_demo_products_and_workflow_with_new_ids(): void
    {
        User::factory()->create(['role' => 'admin']);
        $realProduct = Product::factory()->withStock(23)->create();
        $realOrderItem = PurchaseOrderItem::factory()->create();
        $this->seed(PurchasePlanDemoSeeder::class);
        $oldPlan = PurchasePlan::sole();
        $oldIds = Product::where('name', 'like', '[DEMO]%')->pluck('id');

        $this->artisan('demo:purchase-plan', ['--reset' => true])->assertSuccessful();

        $newPlan = PurchasePlan::sole();
        $newProducts = Product::where('name', 'like', '[DEMO]%')->get();
        $this->assertModelMissing($oldPlan);
        $this->assertCount(75, $newProducts);
        $this->assertCount(0, $newProducts->pluck('id')->intersect($oldIds));
        $this->assertSame(5, $newPlan->orders()->count());
        $this->assertDatabaseCount('distributor_invoices', 4);
        $this->assertDatabaseCount('distributor_payments', 1);
        $this->assertDatabaseCount('shipments', 2);
        $this->assertSame(75, $newPlan->orders->flatMap->items->pluck('product_id')->unique()->count());
        $this->assertModelExists($realProduct);
        $this->assertSame(23, $realProduct->stock->refresh()->quantity);
        $this->assertModelExists($realOrderItem);
        $firstProduct = Product::where('sku', 'DEMO-PO-001')->sole();
        $this->assertSame([210000, 211000, 212000, 213000, 214000], Product::where('name', $firstProduct->name)->orderBy('purchase_price')->pluck('purchase_price')->map(fn (string|int|float $price): int => (int) $price)->all());
    }

    public function test_reset_is_atomic_when_demo_product_has_operational_sales(): void
    {
        User::factory()->create(['role' => 'admin']);
        $this->seed(PurchasePlanDemoSeeder::class);
        $plan = PurchasePlan::sole();
        $product = Product::where('sku', 'DEMO-PO-001')->sole();
        $saleItem = SaleItem::factory()->create(['product_id' => $product->id]);

        $this->artisan('demo:purchase-plan', ['--reset' => true])->assertFailed();

        $this->assertModelExists($plan);
        $this->assertModelExists($saleItem);
        $this->assertDatabaseCount('products', 75);
        $this->assertDatabaseCount('distributor_payments', 1);
        $this->assertDatabaseCount('shipments', 2);
    }

    #[TestWith(['stock'])]
    #[TestWith(['plan'])]
    #[TestWith(['order'])]
    #[TestWith(['shipment'])]
    #[TestWith(['payment'])]
    public function test_reset_preserves_demo_when_linked_data_is_no_longer_isolated(string $usage): void
    {
        User::factory()->create(['role' => 'admin']);
        $this->seed(PurchasePlanDemoSeeder::class);
        $plan = PurchasePlan::sole();
        $product = Product::where('sku', 'DEMO-PO-001')->sole();
        match ($usage) {
            'stock' => $product->stock()->updateOrCreate([], ['quantity' => 1, 'reserved_quantity' => 0]),
            'plan' => PurchasePlan::factory()->create(['items' => [['product_id' => $product->id]]]),
            'order' => PurchaseOrderItem::factory()->create(['product_id' => $product->id]),
            'shipment' => DistributorInvoice::factory()->create(['shipment_id' => DistributorInvoice::whereNotNull('shipment_id')->firstOrFail()->shipment_id]),
            'payment' => DistributorPayment::factory()->create(['distributor_invoice_id' => DistributorInvoice::firstOrFail()->id]),
        };
        $counts = collect(['products', 'purchase_orders', 'purchase_order_items', 'distributor_invoices', 'distributor_payments', 'shipments'])->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()]);

        $this->artisan('demo:purchase-plan', ['--reset' => true])->assertFailed();

        $this->assertModelExists($plan);
        foreach ($counts as $table => $count) {
            $this->assertDatabaseCount($table, $count);
        }
    }

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
