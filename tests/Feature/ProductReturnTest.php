<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Color;
use App\Models\Distributor;
use App\Models\Product;
use App\Models\ProductReturn;
use App\Models\ProductType;
use App\Models\User;
use App\Services\ProductReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductReturnTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_read_or_change_returns(): void
    {
        $record = ProductReturn::factory()->create();
        foreach (['returns.index', 'returns.products', 'returns.export', 'returns.activity'] as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }
        $this->post(route('returns.store'))->assertRedirect(route('login'));
        $this->patch(route('returns.update', $record))->assertRedirect(route('login'));
        $this->patch(route('returns.cancel', $record))->assertRedirect(route('login'));
        $this->patch(route('returns.bulk'))->assertRedirect(route('login'));
        $this->delete(route('returns.destroy', $record))->assertRedirect(route('login'));
    }

    public function test_creating_return_deducts_stock_and_records_immutable_product_snapshot_and_audit(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->withStock(10)->create(['name' => 'Blok Set Lama', 'purchase_price' => 300000]);
        $payload = ['request_id' => (string) Str::uuid(), 'product_id' => $product->id, 'quantity' => 3, 'note_status' => 'pending'];
        $this->actingAs($user)->post(route('returns.store'), $payload)->assertRedirect(route('returns.index'))->assertSessionHasNoErrors();
        $record = ProductReturn::query()->sole();
        $this->assertSame(7, $product->stock->quantity);
        $this->assertSame('Blok Set Lama', $record->product_snapshot['name']);
        $this->assertDatabaseHas('stock_movements', ['reference_id' => $record->id, 'reference_type' => ProductReturn::class, 'quantity' => -3, 'stock_before' => 10, 'stock_after' => 7, 'created_by' => $user->id]);
        $this->assertDatabaseHas('product_return_events', ['product_return_id' => $record->id, 'action' => 'created', 'stock_delta' => -3]);
        $product->update(['name' => 'Blok Baru', 'purchase_price' => 500000]);
        $this->get(route('returns.index'))->assertOk()->assertSee('Blok Set Lama')->assertSee('Rp 300.000')->assertDontSee('Blok Baru');
    }

    public function test_replaying_create_does_not_deduct_stock_twice(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->withStock(10)->create();
        $payload = ['request_id' => (string) Str::uuid(), 'product_id' => $product->id, 'quantity' => 3, 'note_status' => 'credited'];
        $this->actingAs($user)->post(route('returns.store'), $payload)->assertRedirect();
        $this->post(route('returns.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('product_returns', 1);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertDatabaseCount('product_return_events', 1);
        $this->assertSame(7, $product->stock->quantity);
    }

    public function test_reserved_stock_cannot_be_returned_and_failure_rolls_back_every_record(): void
    {
        $product = Product::factory()->withStock(5)->create();
        $product->stock()->update(['reserved_quantity' => 4]);
        $this->actingAs(User::factory()->create())->postJson(route('returns.store'), [
            'request_id' => (string) Str::uuid(), 'product_id' => $product->id, 'quantity' => 2, 'note_status' => 'pending',
        ])->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->assertDatabaseCount('product_returns', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseCount('product_return_events', 0);
        $this->assertSame(5, $product->stock->quantity);
    }

    public function test_a_reused_request_token_with_changed_payload_is_rejected(): void
    {
        $product = Product::factory()->withStock(10)->create();
        $user = User::factory()->create();
        $record = $this->createReturn($user, $product, 2);
        $this->actingAs($user)->postJson(route('returns.store'), [
            'request_id' => $record->request_id, 'product_id' => $product->id, 'quantity' => 3, 'note_status' => 'pending',
        ])->assertUnprocessable()->assertJsonValidationErrors('request_id');
        $this->assertSame(8, $product->stock()->sole()->quantity);
        $this->assertDatabaseCount('product_returns', 1);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_invalid_form_shapes_keep_the_editor_renderable_after_validation_failure(): void
    {
        $this->actingAs(User::factory()->create())->from(route('returns.index', ['create' => 1]))
            ->post(route('returns.store'), ['_dialog' => 'return-create', 'product_id' => ['bad'], 'quantity' => ['bad'], 'request_id' => ['bad']])
            ->assertRedirect()->assertSessionHasErrors(['product_id', 'quantity', 'request_id']);
        $this->withSession(['_old_input' => ['_dialog' => 'return-create', 'product_id' => ['bad'], 'quantity' => ['bad'], 'request_id' => ['bad']]])
            ->get(route('returns.index', ['create' => 1]))->assertOk()->assertSee('Tambah Retur');
    }

    public function test_bulk_quantity_changes_apply_per_product_and_duplicate_selections_are_rejected(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->withStock(12)->create();
        $first = $this->createReturn($user, $product, 1);
        $second = $this->createReturn($user, $product, 2);
        $this->actingAs($user)->patch(route('returns.bulk'), [
            'selected' => [['id' => $first->id, 'version' => 1], ['id' => $second->id, 'version' => 1]], 'quantity' => 3,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(6, $product->stock()->sole()->quantity);
        $this->assertSame(3, $first->refresh()->quantity);
        $this->assertSame(3, $second->refresh()->quantity);
        $this->patchJson(route('returns.bulk'), [
            'selected' => [['id' => $first->id, 'version' => 2], ['id' => $first->id, 'version' => 2]], 'quantity' => 4,
        ])->assertUnprocessable()->assertJsonValidationErrors('selected.0.id');
        $this->assertSame(6, $product->stock()->sole()->quantity);
    }

    public function test_editing_quantity_only_moves_the_difference_and_note_changes_never_move_stock(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->withStock(10)->create();
        $record = $this->createReturn($user, $product, 3);
        $this->actingAs($user)->patch(route('returns.update', $record), ['version' => 1, 'quantity' => 5])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(5, $product->stock()->sole()->quantity);
        $this->patch(route('returns.update', $record), ['version' => 2, 'quantity' => 2])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(8, $product->stock()->sole()->quantity);
        $this->patch(route('returns.update', $record), ['version' => 3, 'note_status' => 'credited'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('stock_movements', 3);
        $this->assertDatabaseHas('product_returns', ['id' => $record->id, 'quantity' => 2, 'note_status' => 'credited', 'version' => 4]);
        $event = $record->events()->first();
        $this->assertSame(0, $event->stock_delta);
        $this->assertSame('pending', $event->before_state['note_status']);
        $this->assertSame('credited', $event->after_state['note_status']);
    }

    public function test_partial_and_full_cancellation_restore_only_the_selected_units_and_reject_stale_submissions(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->withStock(10)->create();
        $record = $this->createReturn($user, $product, 4);
        $this->actingAs($user)->patch(route('returns.cancel', $record), ['version' => 1, 'quantity' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(7, $product->stock()->sole()->quantity);
        $this->assertDatabaseHas('product_returns', ['id' => $record->id, 'quantity' => 3, 'status' => 'active']);
        $this->patchJson(route('returns.cancel', $record), ['version' => 1, 'quantity' => 1])->assertUnprocessable()->assertJsonValidationErrors('version');
        $this->patchJson(route('returns.cancel', $record), ['version' => 2, 'quantity' => 4])->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->patch(route('returns.cancel', $record), ['version' => 2, 'quantity' => 3])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(10, $product->stock()->sole()->quantity);
        $this->assertDatabaseHas('product_returns', ['id' => $record->id, 'quantity' => 0, 'status' => 'cancelled']);
        $this->patchJson(route('returns.cancel', $record), ['version' => 3, 'quantity' => 1])->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->assertDatabaseCount('stock_movements', 3);
        $this->get(route('returns.index'))->assertOk()->assertViewHas('summary', ['products' => 0, 'units' => 0, 'credited' => 0, 'pending' => 0]);
    }

    public function test_delete_restores_remaining_stock_and_preserves_searchable_audit_after_soft_deletion(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->withStock(10)->create(['name' => 'Produk Dihapus']);
        $record = $this->createReturn($user, $product, 4);
        $this->actingAs($user)->patch(route('returns.cancel', $record), ['version' => 1, 'quantity' => 1])->assertRedirect();
        $product->delete();
        $this->delete(route('returns.destroy', $record), ['version' => 2])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSoftDeleted($record);
        $this->assertSame(10, $product->stock()->sole()->quantity);
        $this->assertDatabaseHas('product_return_events', ['product_return_id' => $record->id, 'action' => 'deleted', 'stock_delta' => 3]);
        $this->delete(route('returns.destroy', $record), ['version' => 2])->assertNotFound();
        $this->get(route('returns.activity', ['search' => 'Produk Dihapus']))->assertOk()->assertSee('Hapus Data Retur')->assertSee('Produk Dihapus');
        $this->get(route('returns.index', ['edit' => $record->id, 'mode' => 'activity']))->assertOk()->assertSee('Data Retur Dihapus');
    }

    public function test_deleting_a_fully_cancelled_return_does_not_restore_stock_again(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->withStock(8)->create();
        $record = $this->createReturn($user, $product, 3);
        $this->actingAs($user)->patch(route('returns.cancel', $record), ['version' => 1, 'quantity' => 3])->assertRedirect();
        $this->delete(route('returns.destroy', $record), ['version' => 2])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(8, $product->stock()->sole()->quantity);
        $this->assertDatabaseCount('stock_movements', 2);
    }

    public function test_bulk_edit_is_atomic_when_one_product_has_insufficient_stock(): void
    {
        $user = User::factory()->create();
        $firstProduct = Product::factory()->withStock(10)->create();
        $secondProduct = Product::factory()->withStock(1)->create();
        $first = $this->createReturn($user, $firstProduct, 1);
        $second = $this->createReturn($user, $secondProduct, 1);
        $this->actingAs($user)->patchJson(route('returns.bulk'), [
            'selected' => [['id' => $first->id, 'version' => 1], ['id' => $second->id, 'version' => 1]],
            'quantity' => 2, 'note_status' => 'credited',
        ])->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->assertSame(9, $firstProduct->stock()->sole()->quantity);
        $this->assertSame(0, $secondProduct->stock()->sole()->quantity);
        $this->assertSame(1, $first->refresh()->quantity);
        $this->assertSame('pending', $first->note_status);
        $this->assertDatabaseCount('product_return_events', 2);
        $this->assertDatabaseCount('stock_movements', 2);
    }

    public function test_bulk_status_changes_and_stale_updates_have_no_inventory_side_effects(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->withStock(10)->create();
        $first = $this->createReturn($user, $product, 1);
        $second = $this->createReturn($user, $product, 2);
        $this->actingAs($user)->patch(route('returns.bulk'), [
            'selected' => [['id' => $first->id, 'version' => 1], ['id' => $second->id, 'version' => 1]], 'note_status' => 'credited',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('credited', $first->refresh()->note_status);
        $this->assertSame('credited', $second->refresh()->note_status);
        $this->patchJson(route('returns.update', $first), ['version' => 1, 'quantity' => 6])->assertUnprocessable()->assertJsonValidationErrors('version');
        $this->assertSame(7, $product->stock()->sole()->quantity);
        $this->assertDatabaseCount('stock_movements', 2);
    }

    public function test_filters_card_view_and_csv_use_the_same_historical_metadata(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create();
        $color = Color::factory()->create();
        $type = ProductType::factory()->create();
        $supplier = Distributor::factory()->create();
        $product = Product::factory()->withStock(10)->create(['name' => '=Produk Pilihan', 'brand_id' => $brand->id, 'color_id' => $color->id, 'type_id' => $type->id, 'product_kind' => 'non_daily']);
        $product->distributors()->attach($supplier);
        $record = $this->createReturn($user, $product, 2);
        $this->createReturn($user, Product::factory()->withStock(10)->create(['name' => 'Produk Lain']), 1);
        $filters = ['search' => 'Pilihan', 'kind' => 'non_daily', 'brand_ids' => [$brand->id], 'type_ids' => [$type->id], 'color_ids' => [$color->id], 'distributor_ids' => [$supplier->id], 'note_status' => 'pending', 'view' => 'cards'];
        $response = $this->actingAs($user)->get(route('returns.index', $filters));
        $response->assertOk()->assertSee('product-card', false)->assertSee('=Produk Pilihan')->assertDontSee('Produk Lain');
        $this->assertSame([$record->id], $response->viewData('returns')->modelKeys());
        $response->assertViewHas('summary', ['products' => 1, 'units' => 2, 'credited' => 0, 'pending' => 1]);
        $export = $this->get(route('returns.export', $filters))->assertOk()->assertDownload();
        $this->assertStringContainsString("'=Produk Pilihan", $export->streamedContent());
        $this->assertStringNotContainsString('Produk Lain', $export->streamedContent());
    }

    public function test_product_lookup_only_returns_matching_active_products_and_available_stock(): void
    {
        $product = Product::factory()->withStock(8)->create(['name' => 'Cari Saya']);
        $product->stock()->update(['reserved_quantity' => 3]);
        Product::factory()->inactive()->create(['name' => 'Cari Nonaktif']);
        $this->actingAs(User::factory()->create())->getJson(route('returns.products', ['search' => 'Cari']))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $product->id)->assertJsonPath('data.0.available', 5);
    }

    public function test_all_dialogs_render_with_escaped_product_names(): void
    {
        $user = User::factory()->create();
        $record = $this->createReturn($user, Product::factory()->withStock(5)->create(['name' => '<script>evil()</script>']), 2);
        $this->actingAs($user);
        foreach (['edit', 'status', 'cancel', 'delete', 'activity'] as $mode) {
            $this->get(route('returns.index', ['edit' => $record->id, 'mode' => $mode]))->assertOk()->assertSee('&lt;script&gt;evil()&lt;/script&gt;', false)->assertDontSee('<script>evil()</script>', false);
        }
        $this->get(route('returns.index', ['create' => 1]))->assertOk()->assertSee('Tambah Retur');
    }

    #[DataProvider('invalidReturnData')]
    public function test_invalid_return_input_is_rejected_without_moving_stock(array $data, string $field): void
    {
        $product = Product::factory()->withStock(5)->create();
        $payload = array_replace(['request_id' => (string) Str::uuid(), 'product_id' => $product->id, 'quantity' => 1, 'note_status' => 'pending'], $data);
        $this->actingAs(User::factory()->create())->postJson(route('returns.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('product_returns', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public static function invalidReturnData(): array
    {
        return [
            'zero' => [['quantity' => 0], 'quantity'],
            'negative' => [['quantity' => -1], 'quantity'],
            'fraction' => [['quantity' => 1.5], 'quantity'],
            'too large' => [['quantity' => 100000001], 'quantity'],
            'unknown product' => [['product_id' => 999999], 'product_id'],
            'invalid note' => [['note_status' => 'anything'], 'note_status'],
            'missing token' => [['request_id' => null], 'request_id'],
        ];
    }

    private function createReturn(User $user, Product $product, int $quantity): ProductReturn
    {
        return app(ProductReturnService::class)->create(['request_id' => (string) Str::uuid(), 'product_id' => $product->id, 'quantity' => $quantity, 'note_status' => 'pending'], $user);
    }
}
