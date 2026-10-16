<?php

namespace App\Http\Controllers;

use App\Http\Requests\CatalogueFilterRequest;
use App\Http\Requests\SaleCorrectionRequest;
use App\Http\Requests\TransactionHistoryRequest;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\CatalogueService;
use App\Services\CsvExportService;
use App\Services\ExcelExportService;
use App\Services\SaleCorrectionService;
use App\Services\TransactionHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionHistoryController extends Controller
{
    public function index(TransactionHistoryRequest $request, TransactionHistoryService $history): View
    {
        $filters = $request->validated();
        $editId = $request->input('edit') ?? old('_sale_id');
        $editing = is_string($editId) && Str::isUuid($editId) ? $this->detail(Sale::findOrFail($editId)) : null;

        return view('transactions.index', [
            'transactions' => $history->listing($filters)->paginate((int) ($filters['per_page'] ?? 10))->withQueryString(),
            'summary' => $history->summary($filters),
            'cashiers' => User::query()->select('id', 'name')->orderBy('name')->get(),
            'paymentMethods' => Payment::query()->select('method')->distinct()->orderBy('method')->pluck('method'),
            'editing' => $editing,
            'invoice' => $request->filled('invoice') ? $this->detail(Sale::findOrFail($request->input('invoice'))) : null,
        ]);
    }

    public function show(Sale $sale): View
    {
        $this->detail($sale);

        return view('transactions.show', ['sale' => $sale]);
    }

    private function detail(Sale $sale): Sale
    {
        return $sale->load(['user:id,name', 'items.product' => fn ($query) => $query->withTrashed(), 'payments', 'corrections']);
    }

    public function update(SaleCorrectionRequest $request, Sale $sale, SaleCorrectionService $corrections): RedirectResponse
    {
        $corrections->update($sale, $request->validated(), $request->user());

        return redirect()->route('transactions.index', ['invoice' => $sale->id])->with('success', 'Transaksi berhasil dikoreksi dan stok disesuaikan.');
    }

    public function products(CatalogueFilterRequest $request, CatalogueService $catalogue): JsonResponse
    {
        return response()->json(['data' => $catalogue->products($request->safe()->only('search'))->limit(20)->get()->map(fn (Product $product): array => [
            'id' => $product->id, 'name' => $product->name, 'sku' => $product->sku,
            'price' => (float) $product->selling_price,
            'available' => max(0, ($product->stock?->quantity ?? 0) - ($product->stock?->reserved_quantity ?? 0)),
        ])]);
    }

    public function export(TransactionHistoryRequest $request, TransactionHistoryService $history, CsvExportService $csv, ExcelExportService $excel): StreamedResponse
    {
        $rows = $history->listing($request->validated())->lazy(200)->map(fn (Sale $sale): array => [
            $sale->invoice_number, $sale->sold_at?->format('Y-m-d H:i:s') ?? '',
            $sale->customer_name ?? '', $sale->customer_phone ?? '', $sale->user?->name ?? '',
            $sale->status, $sale->payment_status, $sale->payments->pluck('method')->unique()->join(', '),
            $sale->items_sum_quantity ?? 0, $sale->subtotal, $sale->discount, $sale->tax,
            $sale->grand_total, $sale->paid_amount, $sale->change_amount,
        ]);
        $headers = [
            'Invoice', 'Tanggal', 'Pelanggan', 'Telepon', 'Kasir', 'Status Transaksi', 'Status Pembayaran',
            'Metode Pembayaran', 'Jumlah Unit', 'Subtotal', 'Diskon Transaksi', 'Pajak', 'Total', 'Uang Diterima', 'Kembalian',
        ];

        if ($request->query('format') === 'xlsx' || $request->query('format') === 'excel') {
            return $excel->download(
                'histori-transaksi-'.now()->format('Ymd-His').'.xlsx',
                'HISTORI TRANSAKSI KASIR',
                $headers,
                $rows,
                'Laporan Penjualan & Pembayaran Konsumen Bintang Timur Motor'
            );
        }

        return $csv->download('histori-transaksi-'.now()->format('Ymd-His').'.csv', $headers, $rows);
    }
}
