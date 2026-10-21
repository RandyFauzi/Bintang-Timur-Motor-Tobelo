<?php

namespace Tests\Feature;

use App\Models\Distributor;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchasePlan;
use App\Models\User;
use App\Services\ExcelExportService;
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

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return ['request_id' => (string) Str::uuid(), 'version' => 0, 'intent' => 'finalize', 'name' => 'Pengadaan Stok Oktober 2026', 'planned_on' => '2026-10-21', 'items' => [[
            'product_id' => null, 'product_name' => 'Yamalube 02 Oil', 'color' => '', 'product_kind' => 'daily', 'brand' => 'Yamalube', 'part_type' => 'Oli Mesin', 'current_stock' => 40, 'quantity' => 30, 'sku' => '', 'unit' => 'pcs', 'unit_price' => 0,
            'allocations' => [['distributor_id' => Distributor::factory()->create()->id, 'quantity' => 30]],
        ]]];
    }
}
