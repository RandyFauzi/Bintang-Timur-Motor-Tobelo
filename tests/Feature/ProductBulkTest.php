<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductBulkTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_update_or_delete_selected_products(): void
    {
        $this->patch(route('products.bulk.update'))->assertRedirect(route('login'));
        $this->delete(route('products.bulk.destroy'))->assertRedirect(route('login'));
    }

    public function test_bulk_update_changes_only_selected_products_and_requested_fields(): void
    {
        $user = User::factory()->create();
        $first = Product::factory()->withStock(10)->create(['selling_price' => 10000]);
        $second = Product::factory()->withStock(8)->create(['selling_price' => 20000]);
        $other = Product::factory()->withStock(12)->create(['selling_price' => 30000]);
        $brand = Brand::factory()->create();
        $this->actingAs($user)->patch(route('products.bulk.update'), [
            'selected' => [$first->id, $second->id],
            'changes' => ['selling_price' => 45000, 'purchase_price' => null, 'quantity' => 5, 'stock_minimum' => 0, 'product_kind' => 'variation', 'brand_id' => $brand->id],
        ])->assertRedirect(route('products.index'))->assertSessionHasNoErrors();
        foreach ([$first, $second] as $product) {
            $product->refresh();
            $this->assertEquals(45000, $product->selling_price);
            $this->assertEquals(25000, $product->purchase_price);
            $this->assertSame('variation', $product->product_kind);
            $this->assertSame($brand->id, $product->brand_id);
            $this->assertSame(0, $product->stock_minimum);
            $this->assertSame(5, $product->stock->quantity);
        }
        $this->assertEquals(30000, $other->fresh()->selling_price);
        $this->assertSame(12, $other->fresh()->stock->quantity);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $first->id, 'quantity' => -5, 'created_by' => $user->id]);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $second->id, 'quantity' => -3, 'created_by' => $user->id]);
    }

    public function test_reserved_stock_failure_rolls_back_every_product_update(): void
    {
        $first = Product::factory()->withStock(10)->create();
        $second = Product::factory()->withStock(10)->create();
        $second->stock->update(['reserved_quantity' => 6]);
        $this->actingAs(User::factory()->create())->patchJson(route('products.bulk.update'), [
            'selected' => [$first->id, $second->id], 'changes' => ['quantity' => 5, 'selling_price' => 70000],
        ])->assertUnprocessable()->assertJsonValidationErrors('quantity');
        foreach ([$first, $second] as $product) {
            $this->assertSame(10, $product->fresh()->stock->quantity);
            $this->assertEquals(50000, $product->fresh()->selling_price);
        }
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_bulk_delete_soft_deletes_only_selected_products(): void
    {
        $first = Product::factory()->withStock()->create();
        $second = Product::factory()->withStock()->create();
        $other = Product::factory()->create();
        $this->actingAs(User::factory()->create())->delete(route('products.bulk.destroy'), ['selected' => [$first->id, $second->id]])
            ->assertRedirect(route('products.index'))->assertSessionHasNoErrors();
        $this->assertSoftDeleted($first);
        $this->assertSoftDeleted($second);
        $this->assertNotSoftDeleted($other);
        $this->assertDatabaseHas('product_stocks', ['product_id' => $first->id, 'quantity' => 10]);
    }

    public function test_bulk_delete_is_atomic_when_one_product_has_reserved_stock(): void
    {
        $first = Product::factory()->withStock()->create();
        $second = Product::factory()->withStock()->create();
        $second->stock->update(['reserved_quantity' => 1]);
        $this->actingAs(User::factory()->create())->deleteJson(route('products.bulk.destroy'), ['selected' => [$first->id, $second->id]])
            ->assertUnprocessable()->assertJsonValidationErrors('product');
        $this->assertNotSoftDeleted($first);
        $this->assertNotSoftDeleted($second);
    }

    public function test_bulk_operations_reject_invalid_selection_and_empty_or_unsafe_changes(): void
    {
        $product = Product::factory()->create();
        $inactive = Product::factory()->inactive()->create();
        $this->actingAs(User::factory()->create());
        foreach ([[], [$product->id, $product->id], [$product->id, 999999], [$inactive->id]] as $selected) {
            $this->patchJson(route('products.bulk.update'), ['selected' => $selected, 'changes' => ['selling_price' => 0]])->assertUnprocessable();
            $this->deleteJson(route('products.bulk.destroy'), ['selected' => $selected])->assertUnprocessable();
        }
        foreach ([[], ['selling_price' => null], ['selling_price' => -1], ['name' => 'Tidak Diizinkan'], ['brand_id' => 999999]] as $changes) {
            $this->patchJson(route('products.bulk.update'), ['selected' => [$product->id], 'changes' => $changes])->assertUnprocessable();
        }
        $this->assertNotSoftDeleted($product);
        $this->assertEquals(50000, $product->fresh()->selling_price);
    }

    public function test_bulk_controls_render_in_both_views_and_validation_reopens_dialog(): void
    {
        $product = Product::factory()->create();
        $this->actingAs(User::factory()->create());
        foreach (['list', 'cards'] as $view) {
            $this->get(route('products.index', ['view' => $view]))->assertOk()->assertSee('Hapus Terpilih')->assertSee('Edit Massal');
        }
        $this->followingRedirects()->from(route('products.index'))->patch(route('products.bulk.update'), [
            '_dialog' => 'product-bulk-edit', 'selected' => [$product->id], 'changes' => ['selling_price' => null],
        ])->assertOk()->assertSee('Isi minimal satu kolom untuk edit massal.');
    }
}
