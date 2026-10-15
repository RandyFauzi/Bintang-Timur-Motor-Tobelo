<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleCorrectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_correct_sales(): void
    {
        $sale = Sale::factory()->create();
        $this->patch(route('transactions.update', $sale))->assertRedirect(route('login'));
    }

    public function test_correction_keeps_historical_price_moves_only_stock_difference_and_preserves_payments(): void
    {
        $product = Product::factory()->withStock(8)->create(['selling_price' => 999999]);
        $sale = Sale::factory()->create();
        $item = SaleItem::factory()->for($sale)->for($product)->create(['quantity' => 2, 'unit_price' => 50000, 'subtotal' => 100000]);
        $payment = Payment::factory()->for($sale)->create(['amount' => 100000]);
        $this->actingAs(User::factory()->create())->patch(route('transactions.update', $sale), [
            'version' => 1, 'items' => [['item_id' => $item->id, 'product_id' => $product->id, 'quantity' => 3, 'unit_price' => 1]],
            'note' => 'Tambah satu unit',
        ])->assertRedirect(route('transactions.index', ['invoice' => $sale->id]))->assertSessionHasNoErrors();
        $this->assertSame(7, $product->stock()->sole()->quantity);
        $this->assertDatabaseHas('sales', ['id' => $sale->id, 'grand_total' => 150000, 'version' => 2, 'payment_status' => 'partial']);
        $this->assertDatabaseHas('sale_items', ['id' => $item->id, 'unit_price' => 50000, 'quantity' => 3, 'subtotal' => 150000]);
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'amount' => 100000]);
        $this->assertDatabaseHas('stock_movements', ['reference_id' => $sale->id, 'quantity' => -1, 'type' => 'sale_correction']);
        $this->assertDatabaseHas('sale_corrections', ['sale_id' => $sale->id, 'note' => 'Tambah satu unit']);
        $this->get(route('transactions.index', ['invoice' => $sale->id]))->assertOk()->assertSee('Tambah satu unit');
    }

    public function test_replacing_a_product_restores_removed_stock_and_deducts_new_stock_atomically(): void
    {
        $old = Product::factory()->withStock(8)->create();
        $new = Product::factory()->withStock(5)->create(['selling_price' => 30000]);
        $sale = Sale::factory()->create();
        SaleItem::factory()->for($sale)->for($old)->create(['quantity' => 2, 'product_name' => 'Produk Snapshot']);
        $this->actingAs(User::factory()->create())->patch(route('transactions.update', $sale), [
            'version' => 1, 'items' => [['product_id' => $new->id, 'quantity' => 1]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(10, $old->stock()->sole()->quantity);
        $this->assertSame(4, $new->stock()->sole()->quantity);
        $this->assertDatabaseHas('sales', ['id' => $sale->id, 'grand_total' => 30000]);
        $this->assertDatabaseCount('sale_items', 1);
        $this->assertCount(1, $sale->corrections);
        $this->assertSame('Produk Snapshot', $sale->corrections->first()->before_state['items'][0]['product_name']);
    }

    public function test_insufficient_reserved_stock_rolls_back_items_totals_and_audit(): void
    {
        $product = Product::factory()->withStock(2)->create();
        $product->stock()->update(['reserved_quantity' => 2]);
        $sale = Sale::factory()->create();
        $item = SaleItem::factory()->for($sale)->for($product)->create(['quantity' => 2]);
        $this->actingAs(User::factory()->create())->patchJson(route('transactions.update', $sale), [
            'version' => 1, 'items' => [['item_id' => $item->id, 'product_id' => $product->id, 'quantity' => 3]],
        ])->assertUnprocessable()->assertJsonValidationErrors('items');
        $this->assertSame(2, $item->refresh()->quantity);
        $this->assertDatabaseHas('sales', ['id' => $sale->id, 'grand_total' => 100000, 'version' => 1]);
        $this->assertDatabaseCount('sale_corrections', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_stale_versions_cancelled_sales_and_foreign_items_cannot_be_edited(): void
    {
        $product = Product::factory()->withStock(8)->create();
        $sale = Sale::factory()->create();
        $item = SaleItem::factory()->for($sale)->for($product)->create();
        $foreignItem = SaleItem::factory()->create();
        $this->actingAs(User::factory()->create());
        $this->patchJson(route('transactions.update', $sale), ['version' => 2, 'items' => [['item_id' => $item->id, 'product_id' => $product->id, 'quantity' => 1]]])->assertUnprocessable()->assertJsonValidationErrors('version');
        $this->patchJson(route('transactions.update', $sale), ['version' => 1, 'items' => [['item_id' => $foreignItem->id, 'product_id' => $product->id, 'quantity' => 1]]])->assertUnprocessable()->assertJsonValidationErrors('items');
        $sale->update(['status' => 'cancelled']);
        $this->patchJson(route('transactions.update', $sale), ['version' => 1, 'items' => [['item_id' => $item->id, 'product_id' => $product->id, 'quantity' => 1]]])->assertUnprocessable()->assertJsonValidationErrors('version');
        $this->assertDatabaseCount('sale_corrections', 0);
    }

    public function test_editor_and_invoice_render_using_existing_snapshots_and_empty_cart_is_rejected(): void
    {
        $sale = Sale::factory()->create();
        SaleItem::factory()->for($sale)->create(['product_name' => '<script>bad()</script>']);
        $this->actingAs(User::factory()->create())->get(route('transactions.index', ['edit' => $sale->id]))->assertOk()->assertSee('transaction-editor', false)->assertDontSee('<script>bad()</script>', false);
        $this->patchJson(route('transactions.update', $sale), ['version' => 1, 'items' => []])->assertUnprocessable()->assertJsonValidationErrors('items');
        $this->get(route('transactions.index', ['invoice' => $sale->id]))->assertOk()->assertSee('&lt;script&gt;bad()&lt;/script&gt;', false);
    }
}
