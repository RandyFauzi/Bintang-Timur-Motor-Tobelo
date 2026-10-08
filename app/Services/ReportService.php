<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Expense;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * Get basic financial summary for a date range
     */
    public function getFinancialSummary($startDate = null, $endDate = null)
    {
        $startDate = $startDate ? Carbon::parse($startDate)->startOfDay() : now()->startOfDay();
        $endDate = $endDate ? Carbon::parse($endDate)->endOfDay() : now()->endOfDay();

        // Total Penjualan
        $totalSales = Sale::where('status', 'completed')
            ->whereBetween('sold_at', [$startDate, $endDate])
            ->count();

        // Revenue (Pendapatan)
        $revenue = Sale::where('status', 'completed')
            ->whereBetween('sold_at', [$startDate, $endDate])
            ->sum('grand_total');

        // Modal (Cost of Goods Sold)
        $cogs = SaleItem::whereHas('sale', function ($q) use ($startDate, $endDate) {
                $q->where('status', 'completed')
                  ->whereBetween('sold_at', [$startDate, $endDate]);
            })
            // we join products to get historical purchase_price if not stored in sale_item, 
            // but ideally we should store purchase_price snapshot in sale_items!
            // Assuming it's simple and getting from product currently.
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->sum(DB::raw('sale_items.quantity * products.purchase_price'));

        // Gross Profit
        $grossProfit = $revenue - $cogs;

        // Pengeluaran
        $expenses = Expense::whereBetween('expense_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->sum('amount');

        // Net Profit
        $netProfit = $grossProfit - $expenses;

        // Total products sold
        $itemsSold = SaleItem::whereHas('sale', function ($q) use ($startDate, $endDate) {
                $q->where('status', 'completed')
                  ->whereBetween('sold_at', [$startDate, $endDate]);
            })
            ->sum('quantity');

        return [
            'transactions' => $totalSales,
            'items_sold' => $itemsSold,
            'revenue' => $revenue,
            'cogs' => $cogs,
            'gross_profit' => $grossProfit,
            'expenses' => $expenses,
            'net_profit' => $netProfit,
        ];
    }

    /**
     * Get Best Selling Products
     */
    public function getBestSellers($limit = 5, $startDate = null, $endDate = null)
    {
        $startDate = $startDate ? Carbon::parse($startDate)->startOfDay() : now()->subDays(30)->startOfDay();
        $endDate = $endDate ? Carbon::parse($endDate)->endOfDay() : now()->endOfDay();

        return SaleItem::whereHas('sale', function ($q) use ($startDate, $endDate) {
                $q->where('status', 'completed')
                  ->whereBetween('sold_at', [$startDate, $endDate]);
            })
            ->select('product_name', DB::raw('SUM(quantity) as total_sold'))
            ->groupBy('product_name')
            ->orderByDesc('total_sold')
            ->limit($limit)
            ->get();
    }
}
