<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Vite;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TransactionHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_filters_render_with_the_production_asset_manifest(): void
    {
        $vite = app(Vite::class);
        $hotFile = $vite->hotFile();
        $vite->useHotFile(storage_path('framework/testing-history-vite.hot'));

        try {
            $this->actingAs(User::factory()->create())->get(route('transactions.index'))
                ->assertOk()->assertSee($vite->asset('resources/images/icons/wallet.svg'), false);
        } finally {
            $vite->useHotFile($hotFile);
        }
    }

    public function test_guests_cannot_access_history_details_or_exports(): void
    {
        $sale = Sale::factory()->create();
        foreach (['transactions.index' => [], 'transactions.show' => [$sale], 'transactions.export' => []] as $route => $parameters) {
            $this->get(route($route, $parameters))->assertRedirect(route('login'));
        }
    }

    public function test_staff_cannot_access_transaction_history_pages_or_endpoints(): void
    {
        $staff = User::factory()->staff()->create();
        $sale = Sale::factory()->create();

        $this->actingAs($staff);

        $this->get(route('transactions.index'))->assertForbidden();
        $this->get(route('transactions.show', $sale))->assertForbidden();
        $this->get(route('transactions.export'))->assertForbidden();
        $this->get(route('transactions.products'))->assertForbidden();
        $this->patch(route('transactions.update', $sale), [])->assertForbidden();
    }

    public function test_history_includes_all_dates_and_excludes_cancelled_sales_from_revenue(): void
    {
        Sale::factory()->create(['sold_at' => '2020-01-01 00:00:00', 'grand_total' => 120000.50]);
        Sale::factory()->cancelled()->create(['grand_total' => 999999]);
        $response = $this->actingAs(User::factory()->create())->get(route('transactions.index'));
        $response->assertOk()->assertViewHas('summary', ['transactions' => 2, 'completed' => 1, 'cancelled' => 1, 'revenue' => 120000.50])
            ->assertSee('Histori Transaksi')->assertSee('Rp 120.000,50');
        $this->assertSame(2, $response->viewData('transactions')->total());
    }

    public function test_filters_use_inclusive_dates_and_do_not_duplicate_split_payments(): void
    {
        $cashier = User::factory()->create();
        $matching = Sale::factory()->for($cashier)->create(['sold_at' => '2026-10-15 23:59:59', 'customer_name' => 'Budi']);
        Payment::factory()->for($matching)->count(2)->create(['method' => 'transfer', 'amount' => 50000]);
        Sale::factory()->for($cashier)->create(['sold_at' => '2026-10-16 00:00:00', 'customer_name' => 'Budi']);
        Sale::factory()->for($cashier)->cancelled()->create(['sold_at' => '2026-10-15 10:00:00', 'customer_name' => 'Budi']);
        $filters = ['start_date' => '2026-10-15', 'end_date' => '2026-10-15', 'status' => 'completed', 'payment_status' => 'paid', 'payment_method' => 'transfer', 'user_id' => $cashier->id, 'search' => 'Budi', 'view' => 'cards'];
        $response = $this->actingAs($cashier)->get(route('transactions.index', $filters));
        $response->assertOk()->assertSee('product-card', false)->assertViewHas('summary', ['transactions' => 1, 'completed' => 1, 'cancelled' => 0, 'revenue' => 100000.0]);
        $this->assertSame([$matching->id], $response->viewData('transactions')->modelKeys());
    }

    public function test_multiple_cashiers_filter_listing_summary_and_export_together(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $matching = Sale::factory()->for($first)->create(['customer_name' => 'Kasir Pertama']);
        $otherMatching = Sale::factory()->for($second)->create(['customer_name' => 'Kasir Kedua']);
        $excluded = Sale::factory()->create(['customer_name' => 'Kasir Lain']);
        $filters = ['user_id' => [$first->id, $second->id]];
        $this->actingAs($first);

        $response = $this->get(route('transactions.index', $filters))->assertOk();
        $this->assertEqualsCanonicalizing([$matching->id, $otherMatching->id], $response->viewData('transactions')->modelKeys());
        $this->assertSame(2, $response->viewData('summary')['transactions']);
        $this->assertSame(200000.0, $response->viewData('summary')['revenue']);
        $export = $this->get(route('transactions.export', $filters))->assertOk()->streamedContent();
        $this->assertStringContainsString($matching->invoice_number, $export);
        $this->assertStringContainsString($otherMatching->invoice_number, $export);
        $this->assertStringNotContainsString($excluded->invoice_number, $export);
    }

    public function test_search_uses_historical_product_values_even_after_product_deletion(): void
    {
        $sale = Sale::factory()->create();
        $product = Product::factory()->create(['name' => 'Nama Baru']);
        SaleItem::factory()->for($sale)->for($product)->create(['product_name' => 'Oli Lama', 'sku' => 'HIST-SKU', 'barcode' => '123456789']);
        $product->delete();
        $this->actingAs(User::factory()->create());
        foreach (['Oli Lama', 'HIST-SKU', '123456789', $sale->invoice_number] as $term) {
            $response = $this->get(route('transactions.index', ['search' => $term]));
            $response->assertOk();
            $this->assertSame([$sale->id], $response->viewData('transactions')->modelKeys());
        }
        $this->get(route('transactions.show', $sale))->assertOk()->assertSee('Oli Lama')->assertSee('HIST-SKU')->assertDontSee('Nama Baru');
    }

    public function test_search_does_not_match_another_sales_item_through_ungrouped_conditions(): void
    {
        $matching = Sale::factory()->create();
        $other = Sale::factory()->create();
        SaleItem::factory()->for($matching)->create(['sku' => 'EXACT-SKU']);
        SaleItem::factory()->for($other)->create(['sku' => 'OTHER-SKU']);
        $response = $this->actingAs(User::factory()->create())->get(route('transactions.index', ['search' => 'EXACT-SKU']));
        $response->assertOk();
        $this->assertSame([$matching->id], $response->viewData('transactions')->modelKeys());
    }

    public function test_pagination_preserves_filters_and_summary_covers_all_matching_rows(): void
    {
        $user = User::factory()->create();
        Sale::factory()->for($user)->count(7)->create(['customer_name' => 'Pilihan']);
        Sale::factory()->create(['customer_name' => 'Lain']);
        $response = $this->actingAs($user)->get(route('transactions.index', ['search' => 'Pilihan', 'per_page' => 5, 'page' => 2]));
        $response->assertOk()->assertViewHas('summary', ['transactions' => 7, 'completed' => 7, 'cancelled' => 0, 'revenue' => 700000.0]);
        $this->assertCount(2, $response->viewData('transactions')->items());
        $this->assertStringContainsString('search=Pilihan', $response->viewData('transactions')->previousPageUrl());
    }

    public function test_detail_renders_payment_references_and_escapes_customer_input_without_mutation(): void
    {
        $sale = Sale::factory()->create(['customer_name' => '<script>alert(1)</script>', 'paid_amount' => 120000, 'change_amount' => 20000]);
        Payment::factory()->for($sale)->create(['reference_number' => 'BANK-123', 'method' => 'transfer']);
        $before = $sale->refresh()->getAttributes();
        $this->actingAs(User::factory()->create())->get(route('transactions.show', $sale))
            ->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('BANK-123')->assertSee('120.000,00')->assertSee('20.000,00');
        $this->assertSame($before, $sale->refresh()->getAttributes());
        $this->assertDatabaseCount('stock_movements', 0);
        $this->get(route('transactions.show', '00000000-0000-4000-8000-000000000001'))->assertNotFound();
    }

    public function test_export_applies_the_same_filters_and_escapes_spreadsheet_formulas(): void
    {
        $sale = Sale::factory()->create(['customer_name' => '=HYPERLINK("bad")', 'grand_total' => 100000.25]);
        Payment::factory()->for($sale)->create(['method' => 'cash']);
        Sale::factory()->cancelled()->create(['invoice_number' => 'CANCELLED-NOT-EXPORTED']);
        $response = $this->actingAs(User::factory()->create())->get(route('transactions.export', ['status' => 'completed']));
        $response->assertOk()->assertDownload();
        $csv = $response->streamedContent();
        $this->assertStringContainsString($sale->invoice_number, $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString('100000.25', $csv);
        $this->assertStringNotContainsString('CANCELLED-NOT-EXPORTED', $csv);
    }

    #[DataProvider('invalidFilters')]
    public function test_invalid_filters_are_rejected(array $filters, string $field): void
    {
        $this->actingAs(User::factory()->create())->getJson(route('transactions.index', $filters))
            ->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    public static function invalidFilters(): array
    {
        return [
            'reversed dates' => [['start_date' => '2026-10-16', 'end_date' => '2026-10-15'], 'end_date'],
            'invalid date' => [['start_date' => '2026-02-30'], 'start_date'],
            'invalid status' => [['status' => 'deleted'], 'status'],
            'invalid payment' => [['payment_status' => 'whatever'], 'payment_status'],
            'invalid cashier' => [['user_id' => 999999], 'user_id.0'],
            'invalid multiple cashiers' => [['user_id' => [999998, 999999]], 'user_id.1'],
            'unbounded page size' => [['per_page' => 10000], 'per_page'],
            'invalid view' => [['view' => 'raw'], 'view'],
            'invalid search shape' => [['search' => ['value']], 'search'],
        ];
    }

    public function test_pos_checkout_is_available_in_history_and_records_items_payment_and_stock_atomically(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->withStock(5)->create(['selling_price' => 50000, 'name' => 'Oli Checkout']);
        $this->actingAs($user)->postJson(route('pos.checkout'), [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'paid_amount' => 120000, 'payment_method' => 'cash',
        ])->assertOk()->assertJsonPath('success', true);
        $sale = Sale::query()->sole();
        $this->get(route('transactions.show', $sale))->assertOk()->assertSee('Oli Checkout')->assertSee('100.000,00')->assertSee('20.000,00');
        $this->assertDatabaseHas('payments', ['sale_id' => $sale->id, 'amount' => 100000, 'method' => 'cash']);
        $this->assertDatabaseHas('product_stocks', ['product_id' => $product->id, 'quantity' => 3]);
        $this->assertDatabaseHas('sale_items', ['sale_id' => $sale->id, 'product_name' => 'Oli Checkout', 'quantity' => 2]);

        $this->postJson(route('pos.checkout'), [
            'items' => [['product_id' => $product->id, 'quantity' => 4]],
            'paid_amount' => 200000, 'payment_method' => 'cash',
        ])->assertUnprocessable();
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('sale_items', 1);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_admin_can_cancel_completed_transaction_with_stock_reversal_and_audit_trail(): void
    {
        $admin = User::factory()->create();
        $product = Product::factory()->withStock(10)->create(['selling_price' => 50000, 'name' => 'Kampas Rem']);

        $this->actingAs($admin)->postJson(route('pos.checkout'), [
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
            'paid_amount' => 150000,
            'payment_method' => 'cash',
        ])->assertOk();

        $sale = Sale::query()->sole();
        $this->assertDatabaseHas('product_stocks', ['product_id' => $product->id, 'quantity' => 7]);

        $response = $this->actingAs($admin)->post(route('transactions.cancel', $sale), [
            'reason' => 'Pelanggan salah memilih barang',
        ]);

        $response->assertRedirect(route('transactions.index', ['invoice' => $sale->id]));
        $response->assertSessionHas('success');

        $sale->refresh();
        $this->assertSame('cancelled', $sale->status);
        $this->assertSame($admin->id, $sale->cancelled_by);
        $this->assertNotNull($sale->cancelled_at);
        $this->assertSame('Pelanggan salah memilih barang', $sale->cancellation_reason);

        // Stock reversed back to 10
        $this->assertDatabaseHas('product_stocks', ['product_id' => $product->id, 'quantity' => 10]);

        // Stock movement recorded
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'sale_cancellation',
            'quantity' => 3,
            'stock_before' => 7,
            'stock_after' => 10,
        ]);

        // Sale correction audit trail recorded
        $this->assertDatabaseHas('sale_corrections', [
            'sale_id' => $sale->id,
            'user_id' => $admin->id,
            'actor_name' => $admin->name,
            'note' => 'Pembatalan transaksi: Pelanggan salah memilih barang',
        ]);

        // Viewing the invoice now shows the cancellation notice
        $this->get(route('transactions.show', $sale))
            ->assertOk()
            ->assertSee('Transaksi Dibatalkan')
            ->assertSee('Pelanggan salah memilih barang')
            ->assertSee($admin->name);
    }

    public function test_cannot_cancel_already_cancelled_transaction(): void
    {
        $admin = User::factory()->create();
        $product = Product::factory()->withStock(10)->create();
        $sale = Sale::factory()->cancelled()->create();
        SaleItem::factory()->for($sale)->create(['product_id' => $product->id, 'quantity' => 2]);

        $this->actingAs($admin)->post(route('transactions.cancel', $sale), [
            'reason' => 'Coba batalkan lagi',
        ])->assertSessionHasErrors('status');

        $this->assertDatabaseHas('product_stocks', ['product_id' => $product->id, 'quantity' => 10]);
    }

    public function test_cancellation_reason_is_required_and_validated(): void
    {
        $admin = User::factory()->create();
        $sale = Sale::factory()->create();

        $this->actingAs($admin)->post(route('transactions.cancel', $sale), [
            'reason' => '',
        ])->assertSessionHasErrors('reason');

        $this->actingAs($admin)->post(route('transactions.cancel', $sale), [
            'reason' => 'ab',
        ])->assertSessionHasErrors('reason');

        $sale->refresh();
        $this->assertSame('completed', $sale->status);
    }

    public function test_staff_cannot_cancel_transaction(): void
    {
        $staff = User::factory()->staff()->create();
        $sale = Sale::factory()->create();

        $this->actingAs($staff)->post(route('transactions.cancel', $sale), [
            'reason' => 'Staff mencoba membatalkan',
        ])->assertForbidden();

        $sale->refresh();
        $this->assertSame('completed', $sale->status);
    }
}
