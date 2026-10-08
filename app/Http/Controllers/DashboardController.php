<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ReportService;
use App\Models\Product;

class DashboardController extends Controller
{
    public function index(ReportService $reportService)
    {
        $todaySummary = $reportService->getFinancialSummary(now(), now());
        $lowStockCount = Product::whereHas('stock', function($q) {})
                            ->join('product_stocks', 'products.id', '=', 'product_stocks.product_id')
                            ->whereRaw('product_stocks.quantity <= products.stock_minimum')
                            ->count();
        $totalProducts = Product::count();
        
        $topProducts = $reportService->getBestSellers(3);

        return view('dashboard', compact('todaySummary', 'lowStockCount', 'totalProducts', 'topProducts'));
    }
}
