<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransactionHistoryRequest;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\User;
use App\Services\CsvExportService;
use App\Services\TransactionHistoryService;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionHistoryController extends Controller
{
    public function index(TransactionHistoryRequest $request, TransactionHistoryService $history): View
    {
        $filters = $request->validated();

        return view('transactions.index', [
            'transactions' => $history->listing($filters)->paginate((int) ($filters['per_page'] ?? 10))->withQueryString(),
            'summary' => $history->summary($filters),
            'cashiers' => User::query()->select('id', 'name')->orderBy('name')->get(),
            'paymentMethods' => Payment::query()->select('method')->distinct()->orderBy('method')->pluck('method'),
        ]);
    }

    public function show(Sale $sale): View
    {
        $sale->load(['user:id,name', 'items' => fn (HasMany $query): HasMany => $query->orderBy('created_at')->orderBy('id'), 'payments' => fn (HasMany $query): HasMany => $query->orderBy('paid_at')->orderBy('id')]);

        return view('transactions.show', ['sale' => $sale]);
    }

    public function export(TransactionHistoryRequest $request, TransactionHistoryService $history, CsvExportService $csv): StreamedResponse
    {
        $rows = $history->listing($request->validated())->lazy(200)->map(fn (Sale $sale): array => [
            $sale->invoice_number, $sale->sold_at?->format('Y-m-d H:i:s') ?? '',
            $sale->customer_name ?? '', $sale->customer_phone ?? '', $sale->user?->name ?? '',
            $sale->status, $sale->payment_status, $sale->payments->pluck('method')->unique()->join(', '),
            $sale->items_sum_quantity ?? 0, $sale->subtotal, $sale->discount, $sale->tax,
            $sale->grand_total, $sale->paid_amount, $sale->change_amount,
        ]);

        return $csv->download('histori-transaksi-'.now()->format('Ymd-His').'.csv', [
            'Invoice', 'Tanggal', 'Pelanggan', 'Telepon', 'Kasir', 'Status Transaksi', 'Status Pembayaran',
            'Metode Pembayaran', 'Jumlah Unit', 'Subtotal', 'Diskon Transaksi', 'Pajak', 'Total', 'Uang Diterima', 'Kembalian',
        ], $rows);
    }
}
