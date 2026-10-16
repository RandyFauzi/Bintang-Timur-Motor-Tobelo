<?php

namespace Tests\Feature;

use App\Models\Distributor;
use App\Models\Product;
use App\Models\ProductReturn;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogueTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_product_archives_it_without_removing_stock_or_return_history(): void
    {
        $product = Product::factory()->withStock(8)->create();
        $record = ProductReturn::factory()->create(['product_id' => $product->id]);

        $this->actingAs(User::factory()->create())->delete(route('products.destroy', $product))
            ->assertRedirect(route('products.index', ['view' => 'cards']))->assertSessionHasNoErrors();

        $this->assertSoftDeleted($product);
        $this->assertDatabaseHas('product_stocks', ['product_id' => $product->id, 'quantity' => 8]);
        $this->assertModelExists($record);
        $this->get(route('products.index'))->assertDontSee($product->name);
        $this->assertDatabaseHas('activity_logs', ['route_name' => 'products.destroy', 'action' => 'delete', 'result' => 'success']);
    }

    public function test_product_with_reserved_stock_cannot_be_deleted(): void
    {
        $product = Product::factory()->withStock(8)->create();
        $product->stock()->update(['reserved_quantity' => 2]);

        $this->actingAs(User::factory()->create())->deleteJson(route('products.destroy', $product))
            ->assertUnprocessable()->assertJsonValidationErrors('product');

        $this->assertNotSoftDeleted($product);
        $this->assertDatabaseHas('product_stocks', ['product_id' => $product->id, 'quantity' => 8, 'reserved_quantity' => 2]);
    }

    public function test_guests_cannot_delete_products(): void
    {
        $product = Product::factory()->create();

        $this->delete(route('products.destroy', $product))->assertRedirect(route('login'));

        $this->assertNotSoftDeleted($product);
    }

    public function test_guests_cannot_manage_catalogues(): void
    {
        $this->get(route('products.index'))->assertRedirect(route('login'));
        $this->get(route('distributors.index'))->assertRedirect(route('login'));
        $this->postJson(route('products.store'), [])->assertUnauthorized();
        $this->postJson(route('distributors.store'), [])->assertUnauthorized();
    }

    public function test_product_creation_persists_metadata_stock_and_supplier_relations(): void
    {
        $user = User::factory()->create();
        $supplier = Distributor::factory()->create();
        $response = $this->actingAs($user)->post(route('products.store'), [
            'name' => 'Oli Brembo', 'quantity' => 10, 'stock_minimum' => 5, 'purchase_price' => 50000, 'selling_price' => 65000,
            'product_kind' => 'non_daily', 'brand_name' => 'Bremboix', 'type_name' => 'Oli & Cairan', 'color_name' => 'Merah', 'distributor_ids' => [$supplier->id],
        ]);
        $response->assertRedirect(route('products.index'))->assertSessionHasNoErrors();
        $product = Product::where('name', 'Oli Brembo')->firstOrFail();
        $this->assertSame('non_daily', $product->product_kind);
        $this->assertSame('Bremboix', $product->brand->name);
        $this->assertSame('Merah', $product->color->name);
        $this->assertSame(10, $product->stock->quantity);
        $this->assertSame([$supplier->id], $product->distributors->modelKeys());
        $this->assertStringStartsWith('PRD-', $product->sku);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'stock_before' => 0, 'stock_after' => 10, 'quantity' => 10, 'created_by' => $user->id]);
    }

    public function test_editing_stock_records_the_difference_and_keeps_existing_details(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->withStock(10)->create(['name' => 'Oli Tetap']);
        $this->actingAs($user)->patch(route('products.update', $product), ['quantity' => 4, 'stock_minimum' => 2])
            ->assertRedirect(route('products.index'))->assertSessionHasNoErrors();
        $this->assertSame('Oli Tetap', $product->refresh()->name);
        $this->assertSame(4, $product->stock->quantity);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'quantity' => -6, 'stock_before' => 10, 'stock_after' => 4]);
        $this->patch(route('products.update', $product), ['quantity' => 4, 'stock_minimum' => 2])->assertRedirect();
        $this->assertSame(1, StockMovement::where('product_id', $product->id)->count());
    }

    public function test_invalid_product_does_not_create_partial_records(): void
    {
        $this->actingAs(User::factory()->create())->post(route('products.store'), [
            'name' => 'Invalid', 'quantity' => -1, 'stock_minimum' => 0, 'purchase_price' => 1, 'selling_price' => 2, 'distributor_ids' => [99999],
        ])->assertSessionHasErrors(['quantity', 'distributor_ids.0']);
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_edit_can_clear_supplier_and_color_assignments(): void
    {
        $user = User::factory()->create();
        $supplier = Distributor::factory()->create();
        $product = Product::factory()->withStock()->create();
        $product->distributors()->attach($supplier);
        $this->actingAs($user)->patch(route('products.update', $product), ['quantity' => 10, 'stock_minimum' => 5, 'color_name' => '', '_sync_distributors' => 1])->assertRedirect();
        $this->assertCount(0, $product->refresh()->distributors);
        $this->assertNull($product->color_id);
    }

    public function test_product_filters_and_card_view_return_only_matching_records(): void
    {
        $user = User::factory()->create();
        $supplier = Distributor::factory()->create();
        $matching = Product::factory()->withStock(0)->create(['name' => 'Oli Pilihan', 'product_kind' => 'non_daily']);
        $matching->distributors()->attach($supplier);
        Product::factory()->withStock(20)->create(['name' => 'Oli Lain']);
        $response = $this->actingAs($user)->get(route('products.index', ['view' => 'cards', 'kind' => 'non_daily', 'search' => 'Pilihan', 'low_stock' => 1, 'distributor_ids' => [$supplier->id]]));
        $response->assertOk()->assertSee('product-card', false)->assertSee('Oli Pilihan')->assertDontSee('Oli Lain');
        $this->assertSame([$matching->id], $response->viewData('products')->modelKeys());
    }

    public function test_product_editor_keeps_old_input_and_escapes_names(): void
    {
        $product = Product::factory()->withStock()->create(['name' => '<script>alert(1)</script>']);
        $this->actingAs(User::factory()->create())->get(route('products.index', ['edit' => $product->id]))
            ->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_distributors_can_be_added_edited_and_filtered_by_linked_products(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('distributors.store'), ['name' => 'Sentosa', 'phone' => '08123'])->assertRedirect(route('distributors.index'));
        $supplier = Distributor::where('name', 'Sentosa')->firstOrFail();
        $this->patch(route('distributors.update', $supplier), ['name' => 'Sentosa Baru', 'city' => 'Tobelo'])->assertRedirect();
        $product = Product::factory()->create();
        $product->distributors()->attach($supplier);
        Distributor::factory()->create(['name' => 'Tanpa Produk']);
        $response = $this->get(route('distributors.index', ['view' => 'cards', 'has_products' => 1]));
        $response->assertOk()->assertSee('Sentosa Baru')->assertDontSee('Tanpa Produk');
        $this->assertSame(1, $response->viewData('linkedProductCount'));
        $this->assertSame(1, $response->viewData('distributors')->total());
        $this->get(route('distributors.index', ['edit' => $supplier->id]))->assertOk()->assertSee('Edit Distributor');
    }

    public function test_exports_follow_filters_and_neutralize_spreadsheet_formulas(): void
    {
        $user = User::factory()->create();
        Product::factory()->withStock()->create(['name' => '=HYPERLINK("bad")', 'product_kind' => 'non_daily']);
        Product::factory()->withStock()->create(['name' => 'Tidak diekspor', 'product_kind' => 'daily']);
        $response = $this->actingAs($user)->get(route('products.export', ['kind' => 'non_daily']));
        $response->assertOk()->assertDownload();
        $csv = $response->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString('Tidak diekspor', $csv);
        $this->get(route('distributors.export'))->assertOk()->assertDownload();
    }

    public function test_malformed_filter_values_are_rejected(): void
    {
        $this->actingAs(User::factory()->create())->getJson(route('products.index', ['brand_ids' => 'bad', 'per_page' => 999]))
            ->assertUnprocessable()->assertJsonValidationErrors(['brand_ids', 'per_page']);
    }

    public function test_stock_adjustment_cannot_consume_reserved_items_or_save_partial_details(): void
    {
        $product = Product::factory()->withStock(10)->create(['name' => 'Produk Awal']);
        $product->stock->update(['reserved_quantity' => 6]);

        $this->actingAs(User::factory()->create())->patch(route('products.update', $product), [
            'name' => 'Tidak Boleh Tersimpan', 'quantity' => 4, 'stock_minimum' => 1,
        ])->assertSessionHasErrors(['quantity' => 'Jumlah stok tidak boleh kurang dari stok yang sudah dipesan.']);

        $this->assertSame('Produk Awal', $product->refresh()->name);
        $this->assertSame(10, $product->stock->quantity);
        $this->assertDatabaseCount('stock_movements', 0);
    }
}
