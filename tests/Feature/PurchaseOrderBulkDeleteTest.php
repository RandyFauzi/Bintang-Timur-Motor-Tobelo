<?php

namespace Tests\Feature;

use App\Models\DistributorInvoice;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchasePlan;
use App\Models\User;
use App\Services\PurchaseOrderDeletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderBulkDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_action_deletes_unprocessed_entries_and_archives_processed_orders_without_changing_stock(): void
    {
        $draft = PurchasePlan::factory()->create();
        $plain = PurchaseOrder::factory()->create();
        $processed = PurchaseOrder::factory()->create();
        $invoice = DistributorInvoice::factory()->create(['purchase_order_id' => $processed->id, 'paid_cents' => 100]);
        $product = Product::factory()->withStock(7)->create();
        $this->actingAs(User::factory()->admin()->create())->delete(route('purchase-orders.bulk.destroy'), ['selected' => [$this->row($draft), $this->row($plain), $this->row($processed)]])
            ->assertRedirect(route('purchase-orders.index'))->assertSessionHasNoErrors();
        $this->assertModelMissing($draft);
        $this->assertModelMissing($plain);
        $this->assertNotNull($processed->refresh()->archived_at);
        $this->assertModelExists($invoice);
        $this->assertSame(100, $invoice->refresh()->paid_cents);
        $this->assertSame(7, $product->stock->refresh()->quantity);
    }

    public function test_changed_child_order_aborts_every_selected_group(): void
    {
        $draft = PurchasePlan::factory()->create();
        $plan = PurchasePlan::factory()->create(['status' => 'finalized']);
        $orders = PurchaseOrder::factory()->for($plan, 'purchasePlan')->count(2)->create();
        $rows = [$this->row($draft), $this->row($orders->first())];
        $orders->last()->increment('version');
        $this->actingAs(User::factory()->admin()->create())->deleteJson(route('purchase-orders.bulk.destroy'), ['selected' => $rows])
            ->assertUnprocessable()->assertJsonValidationErrors('selected');
        $this->assertModelExists($draft);
        $this->assertModelExists($plan);
        foreach ($orders as $order) {
            $this->assertModelExists($order);
        }
    }

    public function test_changed_invoice_aborts_the_batch_before_any_deletion(): void
    {
        $order = PurchaseOrder::factory()->create();
        $invoice = DistributorInvoice::factory()->create(['purchase_order_id' => $order->id]);
        $row = $this->row($order);
        $invoice->increment('version');
        $this->actingAs(User::factory()->admin()->create())->deleteJson(route('purchase-orders.bulk.destroy'), ['selected' => [$row]])
            ->assertUnprocessable()->assertJsonValidationErrors('selected');
        $this->assertModelExists($order);
        $this->assertModelExists($invoice);
    }

    public function test_duplicate_plan_groups_and_missing_entries_are_rejected(): void
    {
        $plan = PurchasePlan::factory()->create(['status' => 'finalized']);
        $orders = PurchaseOrder::factory()->for($plan, 'purchasePlan')->count(2)->create();
        $this->actingAs(User::factory()->admin()->create());
        $this->deleteJson(route('purchase-orders.bulk.destroy'), ['selected' => [$this->row($orders->first()), $this->row($orders->last())]])
            ->assertUnprocessable()->assertJsonValidationErrors('selected');
        $row = $this->row($orders->first());
        $row['id'] = 999999;
        $this->deleteJson(route('purchase-orders.bulk.destroy'), ['selected' => [$row]])->assertUnprocessable();
        $this->assertModelExists($plan);
    }

    public function test_bulk_action_requires_admin_permission_and_a_bounded_selection(): void
    {
        $draft = PurchasePlan::factory()->create();
        $row = $this->row($draft);
        $this->delete(route('purchase-orders.bulk.destroy'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->staff()->create())->deleteJson(route('purchase-orders.bulk.destroy'), ['selected' => [$row]])->assertForbidden();
        $this->actingAs(User::factory()->admin()->create());
        foreach ([[], array_fill(0, 101, $row), [['id' => $draft->id, 'type' => 'draft']]] as $selected) {
            $this->deleteJson(route('purchase-orders.bulk.destroy'), ['selected' => $selected])->assertUnprocessable();
        }
        $this->assertModelExists($draft);
    }

    public function test_scrolling_returns_only_the_filtered_list_and_preserves_filters(): void
    {
        PurchasePlan::factory()->count(12)->create(['name' => 'PILIHAN']);
        PurchasePlan::factory()->create(['name' => 'LAIN']);
        $response = $this->actingAs(User::factory()->admin()->create())->get(route('purchase-orders.index', ['search' => 'PILIHAN', 'page' => 2, 'per_page' => 5]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertViewIs('purchase-orders.list')->assertDontSee('<html', false)->assertSee('data-procurement-key', false);
        $this->assertSame(12, $response->viewData('orders')->total());
        $this->assertCount(5, $response->viewData('orders'));
        $this->assertStringContainsString('search=PILIHAN', $response->viewData('orders')->nextPageUrl());
    }

    public function test_procurement_has_no_page_size_control_and_loads_additional_data_in_batches(): void
    {
        PurchasePlan::factory()->count(23)->create();
        $this->actingAs(User::factory()->admin()->create());
        $response = $this->get(route('purchase-orders.index'))->assertOk()->assertDontSee('for="per-page"', false);
        $orders = $response->viewData('orders');
        $this->assertSame(23, $orders->total());
        $this->assertLessThan(23, $orders->count());
        $this->assertNotNull($orders->nextPageUrl());
        $next = $this->get($orders->nextPageUrl(), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->assertViewIs('purchase-orders.list');
        $this->assertEmpty(array_intersect($orders->getCollection()->pluck('id')->all(), $next->viewData('orders')->getCollection()->pluck('id')->all()));
    }

    private function row(PurchasePlan|PurchaseOrder $entry): array
    {
        $entry->refresh();

        return ['type' => $entry instanceof PurchasePlan ? 'draft' : 'order', 'id' => $entry->id, 'version' => $entry->version, 'snapshot' => PurchaseOrderDeletionService::selectionSnapshot($entry)];
    }
}
