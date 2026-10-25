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

    public function test_drafts_share_the_table_with_orders_and_status_filters_keep_them_separate(): void
    {
        $draft = PurchasePlan::factory()->create(['items' => [['product_name' => 'Oli', 'quantity' => 7, 'unit_price' => 15000]]]);
        $plan = $this->planWithTwoOrders();
        $this->actingAs(User::factory()->create());

        $response = $this->get(route('purchase-orders.index'))->assertOk()
            ->assertSee($draft->reference)->assertSee($plan->reference)->assertSee('Lanjutkan Draft')->assertSee('105.000,00')
            ->assertDontSee('Draft Rencana Pengadaan</h2>', false);
        $this->assertSame(2, $response->viewData('orders')->total());

        $draftRows = $this->get(route('purchase-orders.index', ['status' => 'draft']))->assertOk()->assertSee($draft->reference)->assertDontSee($plan->reference)->viewData('orders');
        $this->assertSame(1, $draftRows->total());
        $this->assertTrue($draftRows->sole()->is($draft));

        $pending = $this->get(route('purchase-orders.index', ['status' => 'pending']))->assertOk()->assertDontSee($draft->reference)->viewData('orders');
        $this->assertSame(1, $pending->total());
        $this->assertSame(0, $this->get(route('purchase-orders.index', ['archived' => 1, 'status' => 'draft']))->assertOk()->viewData('orders')->total());
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_draft_filters_match_name_reference_date_and_allocated_distributor(bool $stringId): void
    {
        $supplier = Distributor::factory()->create(['name' => 'Pemasok Khusus Draft']);
        $other = Distributor::factory()->create();
        $draft = PurchasePlan::factory()->create(['name' => 'Rencana Oli Khusus', 'planned_on' => '2026-10-10', 'items' => [['quantity' => 5, 'allocations' => [['distributor_id' => null, 'quantity' => 0], ['distributor_id' => $stringId ? (string) $supplier->id : $supplier->id, 'quantity' => 5]]]]]);
        PurchasePlan::factory()->create(['planned_on' => '2026-10-09']);
        $this->actingAs(User::factory()->create());

        foreach ([['search' => 'Rencana Oli Khusus'], ['search' => $draft->reference], ['search' => $supplier->name], ['distributor_id' => $supplier->id], ['from' => '2026-10-10', 'until' => '2026-10-10']] as $filter) {
            $rows = $this->get(route('purchase-orders.index', $filter + ['status' => 'draft']))->assertOk()->viewData('orders');
            $this->assertSame(1, $rows->total());
            $this->assertTrue($rows->sole()->is($draft));
        }
        foreach ([['distributor_id' => $other->id], ['search' => 'Tidak Ada'], ['from' => '2026-10-11']] as $filter) {
            $this->assertSame(0, $this->get(route('purchase-orders.index', $filter + ['status' => 'draft']))->assertOk()->viewData('orders')->total());
        }
    }

    public function test_mixed_pagination_uses_one_total_and_stable_order_with_overlapping_ids(): void
    {
        PurchasePlan::factory()->count(6)->create(['created_at' => '2026-10-10 10:00:00']);
        PurchaseOrder::factory()->count(6)->create(['created_at' => '2026-10-10 10:00:00']);
        $this->actingAs(User::factory()->create());
        $keys = collect();

        foreach ([1 => 5, 2 => 5, 3 => 2] as $page => $count) {
            $rows = $this->get(route('purchase-orders.index', ['per_page' => 5, 'page' => $page]))->assertOk()->viewData('orders');
            $this->assertSame(12, $rows->total());
            $this->assertCount($count, $rows);
            $keys = $keys->concat($rows->getCollection()->map(fn (PurchaseOrder|PurchasePlan $row): string => $row::class.':'.$row->id));
        }
        $this->assertCount(12, $keys->unique());
    }

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

    #[TestWith(['pending', [null, null], 'In Progress'])]
    #[TestWith(['checking', [1, null], 'Sedang diperiksa'])]
    #[TestWith(['matched', [1, 2], 'Selesai'])]
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

    public function test_multiple_statuses_and_distributors_include_matching_drafts_and_grouped_orders_once(): void
    {
        $plan = $this->planWithTwoOrders();
        $suppliers = $plan->orders->pluck('distributor_id')->all();
        $draft = PurchasePlan::factory()->create(['items' => [['quantity' => 2, 'allocations' => [['distributor_id' => $suppliers[0], 'quantity' => 2]]]]]);
        PurchasePlan::factory()->create();
        $excluded = PurchaseOrderItem::factory()->create();
        $this->actingAs(User::factory()->create());

        $response = $this->get(route('purchase-orders.index', ['status' => ['draft', 'pending'], 'distributor_id' => $suppliers]))->assertOk();
        $this->assertSame(2, $response->viewData('orders')->total());
        $response->assertSee($draft->reference)->assertSee($plan->reference)->assertDontSee(PurchaseOrder::findOrFail($excluded->purchase_order_id)->reference);
        $this->assertSame(1, $response->viewData('orders')->getCollection()->whereInstanceOf(PurchaseOrder::class)->count());
        $completed = PurchaseOrder::factory()->create(['distributor_id' => $suppliers[1]]);
        PurchaseOrderItem::factory()->create(['purchase_order_id' => $completed->id, 'quantity' => 1, 'actual_quantity' => 1]);
        $this->assertSame(2, $this->get(route('purchase-orders.index', ['status' => ['pending', 'matched'], 'distributor_id' => $suppliers]))
            ->assertOk()->viewData('orders')->total());
        $this->getJson(route('purchase-orders.index', ['status' => ['draft', 'invalid']]))
            ->assertUnprocessable()->assertJsonValidationErrors('status.1');
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
