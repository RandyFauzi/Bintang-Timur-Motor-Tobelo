<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Color;
use App\Models\Distributor;
use App\Models\Product;
use App\Models\ProductReturn;
use App\Models\ProductType;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\CatalogueService;
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

    public function test_variation_products_can_be_created_edited_filtered_and_exported(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post(route('products.store'), [
            'name' => 'Aksesori Variasi', 'product_kind' => 'variation', 'quantity' => 5,
            'stock_minimum' => 1, 'purchase_price' => 10000, 'selling_price' => 15000,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $product = Product::where('name', 'Aksesori Variasi')->sole();
        Product::factory()->withStock()->create(['name' => 'Produk Harian Lain', 'product_kind' => 'daily']);
        $page = $this->get(route('products.index', ['kind' => 'variation', 'view' => 'cards']));
        $page->assertOk()->assertSee('Produk Variasi')->assertSee('Aksesori Variasi')->assertDontSee('Produk Harian Lain');
        $this->assertSame([$product->id], $page->viewData('products')->modelKeys());
        $this->patch(route('products.update', $product), ['quantity' => 4, 'stock_minimum' => 1, 'product_kind' => 'variation'])->assertSessionHasNoErrors();
        $this->assertSame('variation', $product->fresh()->product_kind);
        $this->assertStringContainsString('Variasi', $this->get(route('products.export', ['kind' => 'variation']))->streamedContent());
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

    public function test_edit_can_clear_color_without_changing_supplier_identity(): void
    {
        $user = User::factory()->create();
        $supplier = Distributor::factory()->create();
        $product = Product::factory()->withStock()->create();
        $product->distributors()->attach($supplier);
        $this->actingAs($user)->patch(route('products.update', $product), ['quantity' => 10, 'stock_minimum' => 5, 'color_name' => ''])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame([$supplier->id], $product->refresh()->distributors->modelKeys());
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

    public function test_product_form_numeric_inputs_do_not_default_to_zero_value(): void
    {
        $user = User::factory()->create();

        // 1. When creating a new product, numeric inputs are empty with placeholder="0" (not value="0")
        $createPage = $this->actingAs($user)->get(route('products.index', ['create' => 1]));
        $createPage->assertOk();

        $content = $createPage->getContent();
        // Ensure no value="0" for purchase_price, selling_price, quantity, stock_minimum in create form
        $this->assertMatchesRegularExpression('/<input[^>]+name="purchase_price"[^>]+value=""[^>]*>/', $content);
        $this->assertMatchesRegularExpression('/<input[^>]+name="selling_price"[^>]+value=""[^>]*>/', $content);
        $this->assertMatchesRegularExpression('/<input[^>]+name="quantity"[^>]+value=""[^>]*>/', $content);
        $this->assertMatchesRegularExpression('/<input[^>]+name="stock_minimum"[^>]+value=""[^>]*>/', $content);

        // Ensure placeholder="0" is present
        $this->assertMatchesRegularExpression('/<input[^>]+name="purchase_price"[^>]+placeholder="0"[^>]*>/', $content);
        $this->assertMatchesRegularExpression('/<input[^>]+name="selling_price"[^>]+placeholder="0"[^>]*>/', $content);
        $this->assertMatchesRegularExpression('/<input[^>]+name="quantity"[^>]+placeholder="0"[^>]*>/', $content);
        $this->assertMatchesRegularExpression('/<input[^>]+name="stock_minimum"[^>]+placeholder="0"[^>]*>/', $content);

        // 2. When editing an existing product, numeric inputs retain actual values
        $product = Product::factory()->withStock(25)->create([
            'purchase_price' => 75000,
            'selling_price' => 95000,
            'stock_minimum' => 8,
        ]);

        $editPage = $this->actingAs($user)->get(route('products.index', ['edit' => $product->id]));
        $editPage->assertOk();
        $editContent = $editPage->getContent();

        $this->assertMatchesRegularExpression('/<input[^>]+name="purchase_price"[^>]+value="75000"[^>]*>/', $editContent);
        $this->assertMatchesRegularExpression('/<input[^>]+name="selling_price"[^>]+value="95000"[^>]*>/', $editContent);
        $this->assertMatchesRegularExpression('/<input[^>]+name="quantity"[^>]+value="25"[^>]*>/', $editContent);
        $this->assertMatchesRegularExpression('/<input[^>]+name="stock_minimum"[^>]+value="8"[^>]*>/', $editContent);
    }

    public function test_product_form_renders_distributor_autocomplete_input(): void
    {
        $user = User::factory()->create();
        $supplier = Distributor::factory()->create(['name' => 'Distributor Unggulan']);
        $product = Product::factory()->withStock(5)->create();
        $product->distributors()->attach($supplier, ['is_primary' => true, 'purchase_price' => $product->purchase_price]);

        $response = $this->actingAs($user)->get(route('products.index', ['edit' => $product->id]));
        $response->assertOk();
        $content = $response->getContent();

        // Autocomplete input is rendered instead of dropdown button
        $this->assertStringContainsString('distributorAutocomplete(', $content);
        $this->assertMatchesRegularExpression('/<input[^>]+id="distributor_name"[^>]+name="distributor_name"[^>]*>/', $content);
        $this->assertStringContainsString('Distributor Unggulan', $content);
    }

    public function test_product_can_be_created_with_new_or_existing_distributor_name(): void
    {
        $user = User::factory()->create();

        // 1. Existing distributor by name
        $existing = Distributor::factory()->create(['name' => 'PT Makmur Motor']);
        $this->actingAs($user)->post(route('products.store'), [
            'name' => 'Ban Luar IRC',
            'purchase_price' => 150000,
            'selling_price' => 180000,
            'quantity' => 10,
            'stock_minimum' => 2,
            'distributor_name' => 'PT Makmur Motor',
        ])->assertRedirect(route('products.index'))->assertSessionHasNoErrors();

        $product1 = Product::where('name', 'Ban Luar IRC')->firstOrFail();
        $this->assertSame([$existing->id], $product1->distributors->modelKeys());

        // 2. Brand new distributor by name (creates automatically)
        $this->actingAs($user)->post(route('products.store'), [
            'name' => 'Rantai Gold SSS',
            'purchase_price' => 120000,
            'selling_price' => 160000,
            'quantity' => 5,
            'stock_minimum' => 1,
            'distributor_name' => 'CV Sumber Rejeki Baru',
        ])->assertRedirect(route('products.index'))->assertSessionHasNoErrors();

        $product2 = Product::where('name', 'Rantai Gold SSS')->firstOrFail();
        $newDistributor = Distributor::where('name', 'CV Sumber Rejeki Baru')->firstOrFail();
        $this->assertSame([$newDistributor->id], $product2->distributors->modelKeys());
    }

    public function test_distributor_store_json_returns_created_payload(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->postJson(route('distributors.store'), [
            'name' => 'Distributor Kilat Express',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Distributor Kilat Express');

        $this->assertDatabaseHas('distributors', ['name' => 'Distributor Kilat Express']);
    }

    public function test_pagination_component_renders_mobile_infinite_scroll_sentinel_and_loading_indicator(): void
    {
        Product::factory()->count(20)->withStock(5)->create();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('products.index', ['view' => 'cards']))->assertOk();

        $response->assertSee('mobileInfiniteScroll', false)
            ->assertSee('mobile-infinite-scroll', false)
            ->assertSee('infinite-scroll-loading', false)
            ->assertSee('Memuat data...')
            ->assertSee('x-ref="sentinel"', false)
            ->assertSee('catalogue-pagination', false);
    }

    public function test_catalogue_filter_options_and_categories_exclude_unused_types_and_brands(): void
    {
        $activeType = ProductType::create(['name' => 'Mesin', 'slug' => 'mesin', 'is_active' => true]);
        $emptyType = ProductType::create(['name' => 'Ban Motor', 'slug' => 'ban-motor', 'is_active' => true]);

        $activeBrand = Brand::create(['name' => 'Yamaha', 'slug' => 'yamaha', 'is_active' => true]);
        $emptyBrand = Brand::create(['name' => 'Aspira', 'slug' => 'aspira', 'is_active' => true]);

        $product = Product::factory()->withStock(10)->create([
            'type_id' => $activeType->id,
            'brand_id' => $activeBrand->id,
            'is_active' => true,
        ]);

        $catalogue = app(CatalogueService::class);
        $options = $catalogue->options();

        $this->assertTrue($options['types']->contains('id', $activeType->id));
        $this->assertFalse($options['types']->contains('id', $emptyType->id));

        $this->assertTrue($options['brands']->contains('id', $activeBrand->id));
        $this->assertFalse($options['brands']->contains('id', $emptyBrand->id));

        $categories = collect($catalogue->categories());
        $this->assertTrue($categories->pluck('name')->contains('Mesin'));
        $this->assertFalse($categories->pluck('name')->contains('Ban Motor'));

        // When product is deleted, activeType and activeBrand also disappear from filter options
        $catalogue->deleteProduct($product);
        $optionsAfter = $catalogue->options();
        $this->assertFalse($optionsAfter['types']->contains('id', $activeType->id));
        $this->assertFalse($optionsAfter['brands']->contains('id', $activeBrand->id));
    }

    public function test_product_table_and_cards_do_not_render_empty_dash_when_product_has_no_color(): void
    {
        $user = User::factory()->create();
        $productWithoutColor = Product::factory()->withStock(5)->create([
            'name' => 'Karburator Shogun 125',
            'color_id' => null,
            'is_active' => true,
        ]);

        $color = Color::create(['name' => 'Hitam Mengkilap', 'is_active' => true]);
        $productWithColor = Product::factory()->withStock(5)->create([
            'name' => 'Spull assy FIZ-R',
            'color_id' => $color->id,
            'is_active' => true,
        ]);

        // Table view (list)
        $responseTable = $this->actingAs($user)->get(route('products.index', ['view' => 'list']))->assertOk();
        $responseTable->assertSee('Karburator Shogun 125')
            ->assertSee('Spull assy FIZ-R')
            ->assertSee('Hitam Mengkilap')
            ->assertDontSee('<span class="mt-1 block text-muted">–</span>', false);

        // Cards view
        $responseCards = $this->actingAs($user)->get(route('products.index', ['view' => 'cards']))->assertOk();
        $responseCards->assertSee('Karburator Shogun 125')
            ->assertSee('Hitam Mengkilap')
            ->assertDontSee('<p class="mt-2 text-sm text-muted">–</p>', false);
    }
}
