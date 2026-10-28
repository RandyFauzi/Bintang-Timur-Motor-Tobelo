<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportFilterRequest;
use App\Http\Requests\StoreExpenseRequest;
use App\Models\CompetitorObservation;
use App\Models\Expense;
use App\Models\WorkType;
use App\Services\CompetitorReportService;
use App\Services\CsvExportService;
use App\Services\ExcelExportService;
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

    public function export(ReportFilterRequest $request, CompetitorReportService $service, CsvExportService $csv, ExcelExportService $excel): StreamedResponse
    {
        $rows = $service->query($request->validated())->lazy(200)->map(fn (CompetitorObservation $row): array => [$row->observed_on->toDateString(), $row->workshop->name, sprintf('%02d.00', $row->observed_hour), $row->workTypes->sum('pivot.quantity'), $row->workTypes->map(fn (WorkType $type): string => $type->name.' ('.$type->pivot->quantity.')')->join(', ')]);
        $headers = ['Tanggal', 'Bengkel', 'Jam', 'Jumlah', 'Jenis Pengerjaan'];

        if ($request->query('format') === 'xlsx' || $request->query('format') === 'excel') {
            return $excel->download(
                'laporan-observasi-'.now()->format('Ymd').'.xlsx',
                'LAPORAN OBSERVASI KOMPETITOR',
                $headers,
                $rows,
                'Monitoring Bengkel & Harga Pasar Sekitar'
            );
        }

        return $csv->download('laporan-'.now()->format('Ymd').'.csv', $headers, $rows);
    }

    public function finance(ReportService $reportService, Request $request): View
    {
        $period = in_array($request->query('period'), ['7days', '30days'], true) ? $request->query('period') : 'today';

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

    public function storeExpense(StoreExpenseRequest $request): RedirectResponse
    {
        Expense::create($request->validated() + ['created_by' => $request->user()->id]);

        return redirect()->back()->with('success', 'Catatan pengeluaran berhasil ditambahkan.');
    }

    public function destroyExpense(Expense $expense): RedirectResponse
    {
        $expense->delete();

        return redirect()->back()->with('success', 'Catatan pengeluaran berhasil dihapus.');
    }
}
