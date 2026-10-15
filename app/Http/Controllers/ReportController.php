<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportFilterRequest;
use App\Models\CompetitorObservation;
use App\Models\Expense;
use App\Models\WorkType;
use App\Services\CompetitorReportService;
use App\Services\CsvExportService;
use App\Services\ReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(ReportFilterRequest $request, CompetitorReportService $service): View
    {
        return view('reports.index', $service->data($request->validated()) + ['reportFilters' => $request->validated()]);
    }

    public function export(ReportFilterRequest $request, CompetitorReportService $service, CsvExportService $csv): StreamedResponse
    {
        $rows = $service->query($request->validated())->lazy(200)->map(fn (CompetitorObservation $row): array => [$row->observed_on->toDateString(), $row->workshop->name, sprintf('%02d.00', $row->observed_hour), $row->workTypes->sum('pivot.quantity'), $row->workTypes->map(fn (WorkType $type): string => $type->name.' ('.$type->pivot->quantity.')')->join(', ')]);

        return $csv->download('laporan-'.now()->format('Ymd').'.csv', ['Tanggal', 'Bengkel', 'Jam', 'Jumlah', 'Jenis Pengerjaan'], $rows);
    }

    public function finance(ReportService $reportService, Request $request): View
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

        $totalExpenses = Expense::whereBetween('expense_date', [$start, $end])->sum('amount');
        $netProfit = $summary['gross_profit'] - $totalExpenses;

        $recentExpenses = Expense::whereBetween('expense_date', [$start, $end])->orderBy('expense_date', 'desc')->take(5)->get();

        return view('reports.finance', compact('summary', 'topProducts', 'period', 'totalExpenses', 'netProfit', 'recentExpenses'));
    }

    public function storeExpense(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'category' => 'required|string|max:100',
            'description' => 'nullable|string',
            'amount' => 'required|numeric|min:1',
            'expense_date' => 'required|date',
        ]);

        $data['created_by'] = auth()->id();
        Expense::create($data);

        return redirect()->back()->with('success', 'Catatan pengeluaran berhasil ditambahkan.');
    }
}
