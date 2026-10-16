<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_period_filters_sales_expenses_and_product_revenue(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 16)->setTime(12, 0));
        $user = User::factory()->create();
        $product = Product::factory()->create(['purchase_price' => 20000]);
        $sale = Sale::factory()->for($user)->create(['sold_at' => now()->subDays(3), 'grand_total' => 100000, 'status' => 'completed']);
        SaleItem::factory()->for($sale)->for($product)->create(['product_name' => 'Produk Terlaris Uji', 'quantity' => 2, 'unit_price' => 50000, 'subtotal' => 100000]);
        Expense::factory()->create(['amount' => 10000, 'expense_date' => now()->subDays(2)->toDateString()]);
        Expense::factory()->create(['amount' => 999999, 'expense_date' => now()->subDays(20)->toDateString()]);
        $response = $this->actingAs($user)->get(route('reports.finance', ['period' => '7days']));
        $response->assertOk()->assertViewHas('totalExpenses', 10000)->assertViewHas('netProfit', 50000)->assertSee('Produk Terlaris Uji')->assertSee('Rp 100.000')->assertSee('expense-form');
        $this->assertSame(100000, (int) $response->viewData('topProducts')->first()->total_revenue);
        $this->get(route('reports.finance'))->assertOk()->assertViewHas('netProfit', 0);
        $this->get(route('reports.finance', ['period' => 'invalid']))->assertViewHas('period', 'today');
    }

    public function test_expense_validation_restores_modal_input_and_valid_write_records_actor(): void
    {
        $user = User::factory()->create();
        $url = route('reports.finance', ['period' => '7days']);
        $this->actingAs($user)->from($url)->post(route('reports.expenses.store'), ['_dialog' => 'expense-form', 'category' => 'Konsumsi', 'amount' => -10, 'expense_date' => now()->toDateString()])->assertSessionHasErrors('amount')->assertRedirect($url);
        $this->get($url)->assertOk()->assertSee('Konsumsi')->assertSee('expense-form');
        $this->from($url)->post(route('reports.expenses.store'), ['category' => 'Konsumsi', 'amount' => 12500.50, 'expense_date' => now()->toDateString(), 'description' => 'Kebutuhan bengkel'])->assertRedirect($url)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('expenses', ['created_by' => $user->id, 'amount' => 12500.50, 'category' => 'Konsumsi']);
        $this->postJson(route('reports.expenses.store'), ['category' => 'Konsumsi', 'amount' => 1e15, 'expense_date' => now()->toDateString()])->assertUnprocessable();
        $this->assertDatabaseCount('expenses', 1);
    }
}
