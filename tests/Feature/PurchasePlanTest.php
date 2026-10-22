<?php

namespace Tests\Feature;

use App\Models\Distributor;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchasePlan;
use App\Models\ShippingService;
use App\Models\User;
use App\Services\ExcelExportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class PurchasePlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_read_or_save_plans_and_catalogue(): void
    {
        $plan = PurchasePlan::factory()->create();
        $this->get(route('purchase-plans.edit', $plan))->assertRedirect(route('login'));
        $this->get(route('purchase-plans.show', $plan))->assertRedirect(route('login'));
        $this->get(route('purchase-plans.products'))->assertRedirect(route('login'));
        $this->get(route('purchase-plans.template'))->assertRedirect(route('login'));
        $this->post(route('purchase-plans.store'))->assertRedirect(route('login'));
        $this->patch(route('purchase-plans.update', $plan))->assertRedirect(route('login'));
        $this->post(route('purchase-plans.import'))->assertRedirect(route('login'));
    }

    public function test_empty_draft_can_be_saved_reopened_and_found_in_list(): void
    {
        $payload = $this->payload();
        $payload['intent'] = 'draft';
        $payload['items'] = [];
        $this->actingAs(User::factory()->create());

        $response = $this->postJson(route('purchase-plans.store'), $payload)->assertOk();

        $plan = PurchasePlan::sole();
        $response->assertJsonPath('redirect', route('purchase-plans.edit', $plan));
        $this->assertSame('draft', $plan->status);
        $this->assertDatabaseCount('purchase_orders', 0);
        $this->get(route('purchase-plans.edit', $plan))->assertOk()->assertSee('Kebutuhan Produk')->assertSee('Alokasi Distributor')->assertSee('Review &amp; Finalisasi', false);
        $this->get(route('purchase-orders.index'))->assertSee('Lanjutkan Draft')->assertSee($plan->name);
    }

    public function test_finalization_splits_quantities_between_distributors_without_changing_catalogue_or_stock(): void
    {
        $product = Product::factory()->withStock(40)->create(['name' => 'Nama Katalog']);
        $first = Distributor::factory()->create();
        $second = Distributor::factory()->create();
        $payload = $this->payload();
        $payload['items'][0]['product_id'] = $product->id;
        $payload['items'][0]['allocations'] = [['distributor_id' => $first->id, 'quantity' => 12], ['distributor_id' => $second->id, 'quantity' => 18]];
        $this->actingAs(User::factory()->create());

        $response = $this->postJson(route('purchase-plans.store'), $payload)->assertOk();

        $plan = PurchasePlan::sole();
        $response->assertJsonPath('redirect', route('purchase-plans.show', $plan));
        $this->assertSame('finalized', $plan->status);
        $this->assertDatabaseCount('purchase_orders', 2);
        $this->assertDatabaseHas('purchase_order_items', ['purchase_order_id' => PurchaseOrder::where('distributor_id', $first->id)->sole()->id, 'product_name' => 'Yamalube 02 Oil', 'quantity' => 12]);
        $this->assertDatabaseHas('purchase_order_items', ['purchase_order_id' => PurchaseOrder::where('distributor_id', $second->id)->sole()->id, 'quantity' => 18]);
        $this->assertSame('Nama Katalog', $product->refresh()->name);
        $this->assertSame(40, $product->stock->quantity);
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseCount('distributor_invoices', 0);
        $this->get(route('purchase-plans.show', $plan))->assertOk()->assertSee($first->name)->assertSee($second->name)->assertSee('Yamalube 02 Oil');
        $this->postJson(route('purchase-plans.store'), $payload)->assertOk();
        $this->assertDatabaseCount('purchase_orders', 2);
        $this->get(route('purchase-plans.edit', $plan))->assertConflict();
    }

    public function test_autosave_returns_draft_identity_and_retries_do_not_duplicate_or_overwrite_changes(): void
    {
        $payload = $this->payload();
        $payload['intent'] = 'draft';
        $payload['autosave'] = true;
        $payload['items'][0]['allocations'] = [['distributor_id' => null, 'quantity' => 0]];
        $this->actingAs(User::factory()->create());

        $this->postJson(route('purchase-plans.store'), $payload)->assertOk()->assertJsonPath('version', 1)->assertSessionMissing('success');
        $plan = PurchasePlan::sole();
        $this->postJson(route('purchase-plans.store'), $payload)->assertOk()
            ->assertJsonPath('version', 1)->assertJsonPath('reference', $plan->reference)
            ->assertJsonPath('save_url', route('purchase-plans.update', $plan))
            ->assertJsonPath('edit_url', route('purchase-plans.edit', $plan));
        $this->assertDatabaseCount('purchase_plans', 1);
        $this->assertDatabaseCount('purchase_orders', 0);

        $payload['items'][0]['quantity'] = 50;
        $this->postJson(route('purchase-plans.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('version');
        $this->assertSame(30, $plan->refresh()->items[0]['quantity']);
        $payload['version'] = 1;
        $this->patchJson(route('purchase-plans.update', $plan), $payload)->assertOk()->assertJsonPath('version', 2);
        $this->assertSame(50, $plan->refresh()->items[0]['quantity']);
        $this->get(route('purchase-plans.edit', $plan))->assertViewHas('initial', fn (array $initial): bool => $initial['items'][0]['quantity'] === 50 && $initial['items'][0]['allocations'][0]['distributor_id'] === null);
        $payload['intent'] = 'finalize';
        $payload['version'] = 2;
        $this->patchJson(route('purchase-plans.update', $plan), $payload)->assertUnprocessable()->assertJsonValidationErrors('items.0.allocations');
        $this->assertSame('draft', $plan->refresh()->status);
        $this->assertDatabaseCount('purchase_orders', 0);
    }

    public function test_draft_updates_require_current_version_and_can_be_finalized(): void
    {
        $payload = $this->payload();
        $payload['intent'] = 'draft';
        $plan = PurchasePlan::factory()->create(['request_id' => $payload['request_id'], 'version' => 2]);
        $this->actingAs(User::factory()->create());
        $payload['version'] = 1;

        $this->patchJson(route('purchase-plans.update', $plan), $payload)->assertUnprocessable()->assertJsonValidationErrors('version');
        $payload['version'] = 2;
        $this->patchJson(route('purchase-plans.update', $plan), $payload)->assertOk();

        $this->assertSame(3, $plan->refresh()->version);
        $payload['intent'] = 'finalize';
        $payload['version'] = 3;
        $this->patchJson(route('purchase-plans.update', $plan), $payload)->assertOk();
        $this->assertSame('finalized', $plan->refresh()->status);
        $payload['intent'] = 'draft';
        $this->patchJson(route('purchase-plans.update', $plan), $payload)->assertUnprocessable()->assertJsonValidationErrors('plan');
    }

    #[TestWith([29])]
    #[TestWith([31])]
    #[TestWith([0])]
    public function test_finalization_rejects_incomplete_or_excess_allocation(int $quantity): void
    {
        $payload = $this->payload();
        $payload['items'][0]['allocations'][0]['quantity'] = $quantity;

        $this->actingAs(User::factory()->create())->postJson(route('purchase-plans.store'), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.allocations');

        $this->assertDatabaseCount('purchase_plans', 0);
        $this->assertDatabaseCount('purchase_orders', 0);
    }

    public function test_duplicate_and_inactive_distributors_are_rejected(): void
    {
        $payload = $this->payload();
        $id = $payload['items'][0]['allocations'][0]['distributor_id'];
        $payload['items'][0]['allocations'] = [['distributor_id' => $id, 'quantity' => 15], ['distributor_id' => $id, 'quantity' => 15]];
        $this->actingAs(User::factory()->create());

        $this->postJson(route('purchase-plans.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('items.0.allocations');
        Distributor::findOrFail($id)->update(['is_active' => false]);
        $this->postJson(route('purchase-plans.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('items.0.allocations.0.distributor_id');

        $this->assertDatabaseCount('purchase_orders', 0);
    }

    public function test_catalogue_search_returns_attributes_and_stock_but_hides_inactive_products(): void
    {
        $product = Product::factory()->withStock(40)->create(['name' => 'Oli Harian', 'is_active' => true]);
        Product::factory()->create(['name' => 'Oli Nonaktif', 'is_active' => false]);

        $this->actingAs(User::factory()->create())->getJson(route('purchase-plans.products', ['search' => 'Oli']))
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.product_id', $product->id)->assertJsonPath('0.current_stock', 40);
    }

    public function test_import_reads_requirements_without_mutating_master_products(): void
    {
        $content = app(ExcelExportService::class)->download('plan.xlsx', 'RENCANA PENGADAAN', ['Nama Produk', 'Warna', 'Tipe Produk', 'Merk', 'Jenis Part', 'Kebutuhan', 'SKU', 'Modal'], [['Oli Import', 'Gold', 'variation', 'Yamaha', 'Oli', 23, 'SKU-IMP', 12500]], forImport: true)->getCallback();
        ob_start();
        $content();
        $bytes = ob_get_clean();

        $this->actingAs(User::factory()->create())->postJson(route('purchase-plans.import'), ['file' => UploadedFile::fake()->createWithContent('plan.xlsx', $bytes)])
            ->assertOk()->assertJsonPath('items.0.product_name', 'Oli Import')->assertJsonPath('items.0.quantity', '23')->assertJsonPath('items.0.color', 'Gold');

        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('purchase_plans', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_import_rejects_corrupt_files_and_template_download_works(): void
    {
        $this->actingAs(User::factory()->create());

        $this->postJson(route('purchase-plans.import'), ['file' => UploadedFile::fake()->createWithContent('broken.xlsx', 'broken')])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->get(route('purchase-plans.template'))->assertDownload('template-rencana-pengadaan.xlsx');
    }

    public function test_draft_to_supplier_bills_cargo_receipt_and_shortage_reorder_remains_connected(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-22 12:00:00', 'Asia/Jayapura'));
        $this->actingAs(User::factory()->create());
        $payload = $this->payload();
        $secondSupplier = Distributor::factory()->create();
        $payload['items'][0]['allocations'][0]['quantity'] = 12;
        $payload['items'][0]['allocations'][] = ['distributor_id' => $secondSupplier->id, 'quantity' => 18];
        $payload['intent'] = 'draft';
        $this->postJson(route('purchase-plans.store'), $payload)->assertOk();
        $plan = PurchasePlan::sole();
        $this->assertDatabaseCount('purchase_orders', 0);

        $payload['version'] = $plan->version;
        $payload['intent'] = 'finalize';
        $this->patchJson(route('purchase-plans.update', $plan), $payload)->assertOk();
        $orders = $plan->orders()->orderBy('id')->get();
        $this->assertCount(2, $orders);
        $carrier = ShippingService::factory()->create(['duration_days' => 7]);

        foreach ($orders as $index => $order) {
            $quantity = [10, 15][$index];
            $this->patchJson(route('purchase-plans.orders.note', [$plan, $order]), [
                'version' => $order->version, 'note_issued' => true,
                'supplier_note_number' => 'NOTA-'.($index + 1), 'supplier_note_on' => '2026-10-21',
                'supplier_note_total' => ['100.25', '200.75'][$index],
                'items' => [['id' => $order->items->sole()->id, 'invoice_quantity' => $quantity]],
            ])->assertOk();
            $this->postJson(route('purchase-plans.orders.finalize', [$plan, $order]), [
                'version' => $order->refresh()->version, 'shipping_service_id' => $carrier->id,
                'origin_port' => ['Surabaya', 'Makassar'][$index], 'departed_on' => '2026-10-21', 'package_count' => 2,
            ])->assertOk();
            $invoice = $order->fresh()->invoice;
            $this->assertSame([10025, 20075][$index], $invoice->total_cents);
            $this->assertSame('2026-10-28', $invoice->shipment->estimated_arrival_on->toDateString());
            $this->post(route('distributor-invoices.pay', $invoice), [
                'request_id' => (string) Str::uuid(), 'version' => $invoice->version,
                'amount' => ['100.25', '200.75'][$index], 'paid_on' => '2026-10-22', 'method' => 'transfer',
            ])->assertRedirect()->assertSessionHasNoErrors();
            $this->patch(route('shipments.arrive', $invoice->shipment), ['version' => $invoice->shipment->version])
                ->assertRedirect()->assertSessionHasNoErrors();
            $this->assertSame(0, $invoice->refresh()->remainingCents());
            $this->assertNotNull($invoice->receivedAt());
            $this->patch(route('purchase-orders.receive', $order), [
                'version' => $order->refresh()->version,
                'items' => [['id' => $order->items->sole()->id, 'actual_quantity' => $quantity]],
            ])->assertRedirect()->assertSessionHasNoErrors();
        }

        $this->assertDatabaseCount('distributor_invoices', 2);
        $this->assertDatabaseCount('shipments', 2);
        $this->assertNotSame($orders[0]->fresh()->invoice->shipment_id, $orders[1]->fresh()->invoice->shipment_id);
        $initial = $this->get(route('purchase-orders.create', ['replan' => $plan->id, 'source_items' => [0]]))
            ->assertOk()->viewData('initial');
        $this->assertSame(5, $initial['items'][0]['quantity']);
        $this->assertSame([], $initial['items'][0]['allocations']);
        $initial['intent'] = 'finalize';
        unset($initial['reference']);
        $initial['items'][0]['allocations'] = [['distributor_id' => $secondSupplier->id, 'quantity' => 5]];
        $this->postJson(route('purchase-plans.store'), $initial)->assertOk();

        $reorder = PurchasePlan::whereKeyNot($plan->id)->sole();
        $this->assertSame(5, $reorder->orders->sole()->items->sole()->quantity);
        $this->assertSame($secondSupplier->id, $reorder->orders->sole()->distributor_id);
        $this->assertDatabaseCount('purchase_orders', 3);
        $this->assertDatabaseCount('distributor_invoices', 2);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_distributor_orders_cannot_be_deleted_out_of_a_finalized_plan(): void
    {
        $this->actingAs(User::factory()->create());
        $this->postJson(route('purchase-plans.store'), $this->payload())->assertOk();
        $order = PurchaseOrder::sole();

        $this->deleteJson(route('purchase-orders.destroy', $order), ['version' => $order->version])
            ->assertUnprocessable()->assertJsonValidationErrors(['purchase_order' => 'Order distributor dari rencana final tidak dapat dihapus secara terpisah.']);

        $this->assertModelExists($order);
        $this->assertDatabaseCount('purchase_order_items', 1);
    }

    #[TestWith(['items'])]
    #[TestWith(['items.0.allocations'])]
    public function test_draft_rejects_objects_in_product_and_allocation_lists(string $path): void
    {
        $payload = $this->payload();
        $payload['intent'] = 'draft';
        data_set($payload, $path, ['product' => data_get($payload, $path)[0]]);

        $this->actingAs(User::factory()->create())->postJson(route('purchase-plans.store'), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors($path);

        $this->assertDatabaseCount('purchase_plans', 0);
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return ['request_id' => (string) Str::uuid(), 'version' => 0, 'intent' => 'finalize', 'name' => 'Pengadaan Stok Oktober 2026', 'planned_on' => '2026-10-21', 'items' => [[
            'product_id' => null, 'product_name' => 'Yamalube 02 Oil', 'color' => '', 'product_kind' => 'daily', 'brand' => 'Yamalube', 'part_type' => 'Oli Mesin', 'current_stock' => 40, 'quantity' => 30, 'sku' => '', 'unit' => 'pcs', 'unit_price' => 0,
            'allocations' => [['distributor_id' => Distributor::factory()->create()->id, 'quantity' => 30]],
        ]]];
    }
}
