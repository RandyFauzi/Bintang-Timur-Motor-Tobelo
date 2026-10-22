<?php

namespace Tests\Feature;

use App\Models\Distributor;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchasePlan;
use App\Models\User;
use Database\Seeders\PurchasePlanDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class PurchaseOrderOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_is_one_parent_row_with_complete_totals_even_when_searching_or_filtering_a_single_distributor(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->seed(PurchasePlanDemoSeeder::class);
        $plan = PurchasePlan::sole();
        $supplier = Distributor::where('name', 'like', '%Surya%')->sole();

        foreach ([[], ['distributor_id' => $supplier->id], ['search' => 'Simulasi PO'], ['search' => $supplier->name]] as $filters) {
            $response = $this->get(route('purchase-orders.index', $filters))->assertOk()
                ->assertSee($plan->reference)->assertSee('5 distributor')->assertSee('1.275');
            $rows = $response->viewData('orders');
            $this->assertSame(1, $rows->total());
            $this->assertSame($plan->id, $rows->sole()->purchase_plan_id);
            $this->assertCount(15, $rows->sole()->purchasePlan->items);
        }
    }

    #[TestWith(['pending', [null, null], 'Belum diperiksa'])]
    #[TestWith(['checking', [1, null], 'Sedang diperiksa'])]
    #[TestWith(['matched', [1, 2], 'Sesuai'])]
    #[TestWith(['discrepancy', [1, 0], 'Ada selisih'])]
    public function test_status_filters_use_all_distributors_in_the_plan(string $status, array $actuals, string $label): void
    {
        $plan = $this->planWithTwoOrders();
        foreach ($plan->orders as $index => $order) {
            $order->items->sole()->update(['actual_quantity' => $actuals[$index]]);
        }
        $this->actingAs(User::factory()->create());

        $response = $this->get(route('purchase-orders.index', ['status' => $status, 'distributor_id' => $plan->orders->first()->distributor_id]))
            ->assertOk()->assertSee($label);

        $this->assertSame(1, $response->viewData('orders')->total());
        foreach (array_diff(['pending', 'checking', 'matched', 'discrepancy'], [$status]) as $other) {
            $this->assertSame(0, $this->get(route('purchase-orders.index', ['status' => $other]))->assertOk()->viewData('orders')->total());
        }
    }

    public function test_pagination_counts_parent_plans_and_legacy_orders_instead_of_distributor_rows(): void
    {
        for ($index = 0; $index < 6; $index++) {
            $this->planWithTwoOrders();
        }
        PurchaseOrderItem::factory()->create();
        $this->actingAs(User::factory()->create());

        $first = $this->get(route('purchase-orders.index', ['per_page' => 5]))->assertOk()->viewData('orders');
        $second = $this->get(route('purchase-orders.index', ['per_page' => 5, 'page' => 2]))->assertOk()->viewData('orders');

        $this->assertSame(7, $first->total());
        $this->assertCount(5, $first);
        $this->assertCount(2, $second);
        $keys = $first->getCollection()->concat($second->getCollection())->map(fn (PurchaseOrder $order): string => $order->purchase_plan_id ? 'plan:'.$order->purchase_plan_id : 'order:'.$order->id);
        $this->assertCount(7, $keys->unique());
    }

    private function planWithTwoOrders(): PurchasePlan
    {
        $plan = PurchasePlan::factory()->create(['status' => 'finalized', 'items' => [['product_name' => 'Oli', 'quantity' => 3, 'unit_price' => 10000]]]);
        foreach ([1, 2] as $quantity) {
            $order = PurchaseOrder::factory()->for($plan, 'purchasePlan')->create();
            PurchaseOrderItem::factory()->create(['purchase_order_id' => $order->id, 'product_name' => 'Oli', 'quantity' => $quantity]);
        }

        return $plan->load('orders.items');
    }
}
