<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ReportService;

class ReportController extends Controller
{
    public function index(ReportService $reportService, Request $request)
    {
        $period = $request->get('period', 'today'); // today, 7days, 30days
        
        if ($period == '7days') {
            $start = now()->subDays(6)->startOfDay();
            $end = now()->endOfDay();
        } elseif ($period == '30days') {
            $start = now()->subDays(29)->startOfDay();
            $end = now()->endOfDay();
        } else {
            $start = now()->startOfDay();
            $end = now()->endOfDay();
        }

        $summary = $reportService->getFinancialSummary($start, $end);
        $topProducts = $reportService->getBestSellers(10, $start, $end);
        
        $totalExpenses = \App\Models\Expense::whereBetween('expense_date', [$start, $end])->sum('amount');
        $netProfit = $summary['gross_profit'] - $totalExpenses;
        
        $recentExpenses = \App\Models\Expense::whereBetween('expense_date', [$start, $end])->orderBy('expense_date', 'desc')->take(5)->get();

        return view('reports.index', compact('summary', 'topProducts', 'period', 'totalExpenses', 'netProfit', 'recentExpenses'));
    }
    
    public function storeExpense(Request $request)
    {
        $data = $request->validate([
            'category' => 'required|string|max:100',
            'description' => 'nullable|string',
            'amount' => 'required|numeric|min:1',
            'expense_date' => 'required|date'
        ]);
        
        $data['created_by'] = auth()->id();
        \App\Models\Expense::create($data);
        
        return redirect()->back()->with('success', 'Catatan pengeluaran berhasil ditambahkan.');
    }
}