<?php

namespace Tests\Feature;

use App\Models\Distributor;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\SaleItem;
use App\Models\User;
use App\Services\SupplierProductService;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SupplierProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_variant_has_its_own_id_cost_barcode_and_stock_and_is_reused(): void
    {
        $first = Distributor::factory()->create();
        $second = Distributor::factory()->create();
        $product = Product::factory()->withStock(14)->create(['purchase_price' => 10000, 'barcode' => 'ORIGINAL']);
        $product->distributors()->attach($first);
        $service = app(SupplierProductService::class);

        $variant = $service->forDistributor($product, $second, 12500);
        $again = $service->forDistributor($product, $second, 30000);

        $this->assertNotSame($product->id, $variant->id);
        $this->assertSame($variant->id, $again->id);
        $this->assertSame(12500.0, (float) $again->purchase_price);
        $this->assertSame(10000.0, (float) $product->fresh()->purchase_price);
        $this->assertSame([$first->id], $product->fresh()->distributors->modelKeys());
        $this->assertSame([$second->id], $variant->distributors->modelKeys());
        $this->assertNull($variant->barcode);
        $this->assertNotSame($product->sku, $variant->sku);
        $this->assertSame(14, $product->stock->refresh()->quantity);
        $this->assertSame(0, $variant->stock->quantity);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_product_form_rejects_multiple_suppliers_and_removing_supplier_without_changing_stock(): void
    {
        $first = Distributor::factory()->create();
        $second = Distributor::factory()->create();
        $product = Product::factory()->withStock(10)->create();
        $product->distributors()->attach($first);
        $this->actingAs(User::factory()->create());

        foreach ([[$first->id, $second->id], []] as $ids) {
            $this->patchJson(route('products.update', $product), ['quantity' => 3, 'stock_minimum' => 0, 'distributor_ids' => $ids])
                ->assertUnprocessable()->assertJsonValidationErrors('distributor_ids');
            $this->assertSame(10, $product->stock->refresh()->quantity);
            $this->assertSame([$first->id], $product->fresh()->distributors->modelKeys());
        }
    }

    public function test_product_distributor_can_be_changed_without_changing_identity_cost_or_stock(): void
    {
        $first = Distributor::factory()->create();
        $second = Distributor::factory()->create();
        $product = Product::factory()->withStock(10)->create(['purchase_price' => 12500]);
        $product->distributors()->attach($first);
        $sku = $product->sku;
        $this->actingAs(User::factory()->create());

        $this->get(route('products.index', ['edit' => $product->id]))->assertOk()->assertSee('product-distributor-picker');
        $this->patch(route('products.update', $product), ['quantity' => 10, 'stock_minimum' => 0, 'distributor_ids' => [$second->id]])
            ->assertRedirect(route('products.index'))->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertSame([$second->id], $product->distributors->modelKeys());
        $this->assertSame($sku, $product->sku);
        $this->assertSame(12500.0, (float) $product->purchase_price);
        $this->assertSame(10, $product->stock->quantity);
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_missing_migration_is_reported_without_a_database_error(): void
    {
        $product = Product::factory()->create();
        $supplier = Distributor::factory()->create();
        Schema::table('products', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('supplier_source_id');
        });
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Pembaruan database produk belum diterapkan.');

        app(SupplierProductService::class)->forDistributor($product, $supplier, 10000);
    }

    public function test_database_rejects_a_second_supplier_for_one_product(): void
    {
        $product = Product::factory()->create();
        $product->distributors()->attach(Distributor::factory()->create());
        $this->expectException(QueryException::class);

        $product->distributors()->attach(Distributor::factory()->create());
    }

    public function test_migration_splits_legacy_suppliers_preserves_stock_and_sales_and_links_existing_po_items(): void
    {
        Schema::table('product_distributors', fn (Blueprint $table) => $table->dropUnique('product_distributors_one_supplier_per_product'));
        $first = Distributor::factory()->create();
        $second = Distributor::factory()->create();
        $product = Product::factory()->withStock(18)->create(['purchase_price' => 10000, 'barcode' => 'OLD-CODE']);
        $product->distributors()->attach($first, ['is_primary' => true, 'purchase_price' => 10000]);
        $product->distributors()->attach($second, ['purchase_price' => 14000]);
        $saleItem = SaleItem::factory()->create(['product_id' => $product->id]);
        $order = PurchaseOrder::factory()->create(['distributor_id' => $second->id]);
        $item = PurchaseOrderItem::factory()->create(['purchase_order_id' => $order->id, 'sku' => $product->sku, 'unit_price' => 13500]);
        $migration = require database_path('migrations/2026_10_23_120036_separate_product_identities_by_distributor.php');

        $migration->up();

        $variant = Product::where('supplier_source_id', $product->id)->sole();
        $this->assertSame(14000.0, (float) $variant->purchase_price);
        $this->assertSame([$second->id], $variant->distributors->modelKeys());
        $this->assertSame([$first->id], $product->fresh()->distributors->modelKeys());
        $this->assertSame($variant->id, $item->refresh()->product_id);
        $this->assertSame('13500.00', $item->unit_price);
        $this->assertSame($product->sku, $item->sku);
        $this->assertSame($product->id, $saleItem->refresh()->product_id);
        $this->assertSame(18, $product->stock->refresh()->quantity);
        $this->assertSame(0, $variant->stock->quantity);
        $this->assertSame('OLD-CODE', $product->fresh()->barcode);
        $migration->up();
        $this->assertDatabaseCount('products', 2);
    }
}
