<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_dashboard_shows_real_totals_and_compares_equal_length_periods(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(12, 0));
        $user = User::factory()->create();
        Sale::factory()->for($user)->create(['grand_total' => 100000, 'sold_at' => '2026-10-09 00:00:00']);
        Sale::factory()->for($user)->create(['grand_total' => 200000, 'sold_at' => '2026-10-15 23:59:59']);
        Sale::factory()->for($user)->create(['grand_total' => 150000, 'sold_at' => '2026-10-08 23:59:59']);
        Sale::factory()->for($user)->create(['grand_total' => 700000, 'sold_at' => '2026-10-01 23:59:59']);
        Sale::factory()->for($user)->cancelled()->create(['grand_total' => 999999, 'sold_at' => '2026-10-10 10:00:00']);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()->assertViewHas('summary', ['revenue' => 300000.0, 'transactions' => 2])
            ->assertViewHas('previousSummary', ['revenue' => 150000.0, 'transactions' => 1])
            ->assertViewHas('changes', ['revenue' => 100.0, 'transactions' => 100.0])
            ->assertSee('Rp 300.000')->assertSee('Pencatatan retur belum tersedia');
        $this->assertSame('2026-10-09', $response->viewData('range')['start']);
        $this->assertSame('2026-10-15', $response->viewData('range')['end']);
        $this->assertSame(7, count($response->viewData('revenueSeries')));
        $this->assertSame(0.0, $response->viewData('revenueSeries')[1]['value']);
        $this->assertSame(200000.0, $response->viewData('revenueSeries')[6]['value']);
    }

    public function test_charts_can_have_independent_date_ranges_and_include_out_of_hours_sales(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 15));
        $user = User::factory()->create();
        Sale::factory()->for($user)->create(['grand_total' => 80000, 'sold_at' => '2026-10-01 07:30:00']);
        Sale::factory()->for($user)->create(['grand_total' => 120000, 'sold_at' => '2026-10-02 22:00:00']);
        Sale::factory()->for($user)->cancelled()->create(['sold_at' => '2026-10-02 22:00:00']);

        $response = $this->actingAs($user)->get(route('dashboard', [
            'start_date' => '2026-10-10', 'end_date' => '2026-10-15',
            'revenue_start_date' => '2026-10-01', 'revenue_end_date' => '2026-10-01',
            'activity_start_date' => '2026-10-02', 'activity_end_date' => '2026-10-02',
        ]));

        $response->assertOk()->assertViewHas('summary', ['revenue' => 0.0, 'transactions' => 0]);
        $this->assertSame([['date' => '2026-10-01', 'label' => '1 Okt', 'value' => 80000.0]], $response->viewData('revenueSeries'));
        $this->assertSame(['label' => '22.00', 'value' => 1], collect($response->viewData('hourlySeries'))->last());
    }

    public function test_best_sellers_use_item_revenue_exclude_cancelled_sales_and_escape_names(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 15));
        $user = User::factory()->create();
        $sale = Sale::factory()->for($user)->create();
        $oldSale = Sale::factory()->for($user)->create(['sold_at' => '2026-09-01']);
        $cancelledSale = Sale::factory()->for($user)->cancelled()->create();
        $product = Product::factory()->create();
        SaleItem::factory()->for($sale)->for($product)->create(['product_name' => '<script>alert(1)</script>', 'quantity' => 3, 'subtotal' => 135000]);
        SaleItem::factory()->for($sale)->for($product)->create(['product_name' => '<script>alert(1)</script>', 'quantity' => 2, 'subtotal' => 100000]);
        SaleItem::factory()->for($oldSale, 'sale')->create(['quantity' => 50]);
        SaleItem::factory()->for($cancelledSale, 'sale')->create(['quantity' => 99]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->assertCount(1, $response->viewData('topProducts'));
        $this->assertSame(5, (int) $response->viewData('topProducts')->first()->total_sold);
        $this->assertSame(235000.0, (float) $response->viewData('topProducts')->first()->revenue);
    }

    public function test_low_stock_includes_missing_stock_and_excludes_inactive_and_deleted_products(): void
    {
        $user = User::factory()->create();
        Product::factory()->create(['name' => 'Stok kosong']);
        Product::factory()->withStock(5)->create(['name' => 'Batas minimum']);
        Product::factory()->withStock(6)->create(['name' => 'Stok aman']);
        Product::factory()->inactive()->withStock(0)->create();
        Product::factory()->withStock(0)->create()->delete();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()->assertSee('Stok kosong')->assertSee('Batas minimum')->assertDontSee('Stok aman');
        $this->assertSame(2, $response->viewData('stockOverview')['count']);
        $this->assertSame(0, (int) $response->viewData('stockOverview')['products']->first()->current_stock);
    }

    public function test_view_all_low_stock_filters_the_product_catalogue(): void
    {
        $user = User::factory()->create();
        Product::factory()->create(['name' => 'Produk perlu ditambah']);
        Product::factory()->withStock(20)->create(['name' => 'Produk stok aman']);

        $this->actingAs($user)->get(route('products.index', ['low_stock' => 1]))
            ->assertOk()->assertSee('Produk perlu ditambah')->assertDontSee('Produk stok aman');
    }

    public function test_empty_dashboard_has_honest_empty_states_without_sample_data(): void
    {
        $this->actingAs(User::factory()->create())->get(route('dashboard'))
            ->assertOk()->assertViewHas('summary', ['revenue' => 0.0, 'transactions' => 0])
            ->assertViewHas('changes', ['revenue' => 0.0, 'transactions' => 0.0])
            ->assertSee('Belum ada produk terjual.')->assertSee('Stok produk aman.')
            ->assertSee('Belum ada penjualan pada periode ini.')
            ->assertDontSee('Oli Federal Matic');
    }

    public function test_first_sales_do_not_fabricate_percentage_growth_from_zero(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 15));
        $user = User::factory()->create();
        Sale::factory()->for($user)->create();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()->assertViewHas('changes', ['revenue' => null, 'transactions' => null])->assertSee('Baru');
    }

    public function test_sales_decline_is_compared_to_the_selected_previous_period(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 15));
        $user = User::factory()->create();
        Sale::factory()->for($user)->create(['grand_total' => 100000, 'sold_at' => '2026-10-01 10:00:00']);
        Sale::factory()->for($user)->create(['grand_total' => 50000, 'sold_at' => '2026-10-02 10:00:00']);

        $this->actingAs($user)->get(route('dashboard', ['start_date' => '2026-10-02', 'end_date' => '2026-10-02']))
            ->assertOk()->assertViewHas('changes', ['revenue' => -50.0, 'transactions' => 0.0])->assertSee('-50%');
    }

    public function test_low_stock_pagination_preserves_the_dashboard_filter(): void
    {
        $user = User::factory()->create();
        Product::factory()->count(16)->withStock(0)->create();
        Product::factory()->withStock(20)->create();

        $response = $this->actingAs($user)->get(route('products.index', ['low_stock' => 1]));

        $response->assertOk();
        $this->assertSame(16, $response->viewData('products')->total());
        $this->assertStringContainsString('low_stock=1', $response->viewData('products')->nextPageUrl());
        $this->get($response->viewData('products')->nextPageUrl())->assertOk()->assertViewHas('products', fn ($products) => $products->count() === 1);
    }

    #[DataProvider('invalidDateFilters')]
    public function test_invalid_dates_return_a_clear_validation_error(array $filters, string $field, string $message): void
    {
        $this->travelTo(now()->setDate(2026, 10, 15));

        $this->actingAs(User::factory()->create())->from(route('dashboard'))
            ->get(route('dashboard', $filters))->assertRedirect(route('dashboard'))->assertSessionHasErrors([$field => $message]);
    }

    public static function invalidDateFilters(): array
    {
        return [
            'missing end' => [['start_date' => '2026-10-01'], 'end_date', 'Tanggal awal dan akhir harus diisi bersama.'],
            'invalid date' => [['start_date' => 'bad', 'end_date' => '2026-10-01'], 'start_date', 'Gunakan tanggal dengan format YYYY-MM-DD.'],
            'reversed dates' => [['start_date' => '2026-10-15', 'end_date' => '2026-10-01'], 'end_date', 'Tanggal akhir harus sama atau setelah tanggal awal.'],
            'future date' => [['start_date' => '2026-10-01', 'end_date' => '2026-10-16'], 'end_date', 'Tanggal tidak boleh melewati hari ini.'],
            'range too long' => [['start_date' => '2025-10-01', 'end_date' => '2026-10-15'], 'end_date', 'Rentang tanggal maksimal 366 hari.'],
            'invalid chart dates' => [['revenue_start_date' => '2026-10-15', 'revenue_end_date' => '2026-10-01'], 'revenue_end_date', 'Tanggal akhir harus sama atau setelah tanggal awal.'],
        ];
    }
}
