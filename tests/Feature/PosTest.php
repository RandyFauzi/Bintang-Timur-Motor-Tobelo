<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Color;
use App\Models\Distributor;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_open_pos_or_scan_or_checkout(): void
    {
        $this->get(route('pos.index'))->assertRedirect(route('login'));
        $this->post(route('pos.scan'))->assertRedirect(route('login'));
        $this->post(route('pos.checkout'))->assertRedirect(route('login'));
    }

    public function test_pos_paginates_six_cards_and_renders_all_transaction_panels(): void
    {
        Product::factory()->withStock()->count(8)->create();
        Product::factory()->inactive()->create();
        $response = $this->actingAs(User::factory()->create())->get(route('pos.index'));
        $response->assertOk()->assertSee('Kasir / POS')->assertSee('Lihat Keranjang')->assertSee('Ringkasan Transaksi')->assertDontSee('Uang Diterima (Tunai)')->assertSee('Simpan Transaksi')->assertSee('Transaksi Berhasil Dicatat!');
        $this->assertCount(6, $response->viewData('catalogueProducts'));
        $this->assertSame(8, $response->viewData('products')->total());
    }

    public function test_pos_filters_metadata_and_returns_available_stock_without_purchase_costs(): void
    {
        $brand = Brand::factory()->create();
        $color = Color::factory()->create();
        $distributor = Distributor::factory()->create();
        $product = Product::factory()->withStock(8)->create(['name' => 'Produk Pilihan', 'brand_id' => $brand->id, 'color_id' => $color->id]);
        $product->distributors()->attach($distributor);
        $product->stock()->update(['reserved_quantity' => 3]);
        Product::factory()->withStock()->create();
        $response = $this->actingAs(User::factory()->create())->get(route('pos.index', ['search' => 'Pilihan', 'brand_ids' => [$brand->id], 'color_ids' => [$color->id], 'distributor_ids' => [$distributor->id]]));
        $response->assertOk();
        $this->assertSame([$product->id], $response->viewData('products')->modelKeys());
        $data = $response->viewData('catalogueProducts')->sole();
        $this->assertSame(5, $data['available']);
        $this->assertSame($brand->name, $data['brand']);
        $this->assertArrayNotHasKey('purchase_price', $data);
        $this->assertArrayNotHasKey('distributors', $data);
        $response->assertDontSee('Semua Distributor')->assertDontSee('<dt>Distributor</dt>', false);
    }

    public function test_barcode_lookup_returns_current_product_metadata_and_excludes_inactive_products(): void
    {
        $product = Product::factory()->withStock(5)->create(['barcode' => 'ABC-123']);
        $product->stock()->update(['reserved_quantity' => 1]);
        Product::factory()->inactive()->create(['barcode' => 'INACTIVE']);
        $this->actingAs(User::factory()->create())->postJson(route('pos.scan'), ['barcode' => 'ABC-123'])
            ->assertOk()->assertJsonPath('data.id', $product->id)->assertJsonPath('data.available', 4);
        $this->postJson(route('pos.scan'), ['barcode' => 'INACTIVE'])->assertNotFound();
        $this->postJson(route('pos.scan'), ['barcode' => []])->assertUnprocessable()->assertJsonValidationErrors('barcode');
    }

    public function test_cash_checkout_returns_receipt_information_and_uses_server_prices(): void
    {
        $product = Product::factory()->withStock(5)->create(['selling_price' => 50000.25]);
        $user = User::factory()->create();
        $response = $this->actingAs($user)->postJson(route('pos.checkout'), [
            'items' => [['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 1]],
            'paid_amount' => 110000, 'payment_method' => 'cash',
        ]);
        $sale = Sale::query()->sole();
        $response->assertOk()->assertJsonPath('success', true)->assertJsonPath('total', 100000.5)->assertJsonPath('change', 9999.5)
            ->assertJsonPath('detail_url', route('transactions.show', $sale))->assertJsonPath('invoice', $sale->invoice_number);
        $this->assertSame(3, $product->stock()->sole()->quantity);
        $this->assertDatabaseHas('payments', ['sale_id' => $sale->id, 'amount' => 100000.5]);
    }

    public function test_checkout_rejects_reserved_stock_and_rolls_back_the_entire_cart(): void
    {
        $first = Product::factory()->withStock(5)->create();
        $reserved = Product::factory()->withStock(5)->create();
        $reserved->stock()->update(['reserved_quantity' => 4]);
        $this->actingAs(User::factory()->create())->postJson(route('pos.checkout'), [
            'items' => [['product_id' => $first->id, 'quantity' => 1], ['product_id' => $reserved->id, 'quantity' => 2]],
            'paid_amount' => 150000, 'payment_method' => 'cash',
        ])->assertUnprocessable()->assertJsonValidationErrors('items');
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame(5, $first->stock()->sole()->quantity);
    }

    public function test_duplicate_products_and_underpayment_are_rejected_without_sale_records(): void
    {
        $product = Product::factory()->withStock(5)->create(['selling_price' => 50000]);
        $this->actingAs(User::factory()->create())->postJson(route('pos.checkout'), [
            'items' => [['product_id' => $product->id, 'quantity' => 1], ['product_id' => $product->id, 'quantity' => 1]],
            'paid_amount' => 100000, 'payment_method' => 'cash',
        ])->assertUnprocessable()->assertJsonValidationErrors('items.0.product_id');
        $this->postJson(route('pos.checkout'), [
            'items' => [['product_id' => $product->id, 'quantity' => 1]], 'paid_amount' => 100, 'payment_method' => 'cash',
        ])->assertUnprocessable()->assertJsonValidationErrors('paid_amount');
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }
}
