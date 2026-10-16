<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductReturnFilterRequest;
use App\Http\Requests\ProductReturnRequest;
use App\Models\Product;
use App\Models\ProductReturn;
use App\Models\ProductReturnEvent;
use App\Services\CatalogueService;
use App\Services\CsvExportService;
use App\Services\ExcelExportService;
use App\Services\ProductReturnService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductReturnController extends Controller
{
    public function index(ProductReturnFilterRequest $request, ProductReturnService $returns, CatalogueService $catalogue): View
    {
        $filters = $request->validated();
        $editingId = $request->input('edit') ?? old('_return_id');
        $editingId = is_string($editingId) && Str::isUuid($editingId) ? $editingId : null;
        $editing = $editingId ? ProductReturn::withTrashed()->with('events')->findOrFail($editingId) : null;
        $oldProductId = filter_var(old('product_id', $request->validated('product_id')), FILTER_VALIDATE_INT);
        $selectedProduct = $oldProductId ? Product::with('stock')->find($oldProductId) : null;
        $requestId = old('request_id');
        $requestId = is_string($requestId) && Str::isUuid($requestId) ? $requestId : (string) Str::uuid();

        return view('returns.index', array_merge($catalogue->options(), [
            'returns' => $returns->query($filters)->orderByDesc('created_at')->orderByDesc('id')
                ->paginate((int) ($filters['per_page'] ?? ($request->input('view') === 'cards' ? 6 : 15)))->withQueryString(),
            'summary' => $returns->summary($filters), 'editing' => $editing,
            'selectedProduct' => $selectedProduct, 'requestId' => $requestId,
        ]));
    }

    public function products(ProductReturnFilterRequest $request, CatalogueService $catalogue): JsonResponse
    {
        $products = $catalogue->products($request->safe()->only('search'))->limit(20)->get()
            ->map(fn (Product $product): array => [
                'id' => $product->id, 'name' => $product->name, 'sku' => $product->sku,
                'available' => max(0, ($product->stock?->quantity ?? 0) - ($product->stock?->reserved_quantity ?? 0)),
            ]);

        return response()->json(['data' => $products]);
    }

    public function store(ProductReturnRequest $request, ProductReturnService $returns): RedirectResponse
    {
        $returns->create($request->validated(), $request->user());

        return redirect()->route('returns.index')->with('success', 'Retur ditambahkan dan stok berhasil dikurangi.');
    }

    public function update(ProductReturnRequest $request, ProductReturn $productReturn, ProductReturnService $returns): RedirectResponse
    {
        $returns->updateMany([['id' => $productReturn->id, 'version' => $request->integer('version')]], $request->safe()->only(['quantity', 'note_status']), $request->user());

        return redirect()->route('returns.index')->with('success', 'Retur berhasil diperbarui.');
    }

    public function bulk(ProductReturnRequest $request, ProductReturnService $returns): RedirectResponse
    {
        $returns->updateMany($request->validated('selected'), $request->safe()->only(['quantity', 'note_status']), $request->user());

        return redirect()->route('returns.index')->with('success', 'Retur terpilih berhasil diperbarui.');
    }

    public function cancel(ProductReturnRequest $request, ProductReturn $productReturn, ProductReturnService $returns): RedirectResponse
    {
        $returns->cancel($productReturn, $request->integer('quantity'), $request->integer('version'), $request->user());

        return redirect()->route('returns.index')->with('success', 'Pembatalan retur berhasil. Stok telah dikembalikan.');
    }

    public function destroy(ProductReturnRequest $request, ProductReturn $productReturn, ProductReturnService $returns): RedirectResponse
    {
        $returns->delete($productReturn, $request->integer('version'), $request->user());

        return redirect()->route('returns.index')->with('success', 'Data retur dihapus. Sisa stok dikembalikan dan log aktivitas tetap tersimpan.');
    }

    public function activity(ProductReturnFilterRequest $request): View
    {
        $query = ProductReturnEvent::with('productReturn');
        if ($search = $request->validated('search')) {
            $query->whereHas('productReturn', fn (Builder $query): Builder => $query->where('product_snapshot->name', 'like', '%'.$search.'%'));
        }

        return view('returns.activity', ['events' => $query->orderByDesc('id')->paginate((int) ($request->validated('per_page') ?? 15))->withQueryString()]);
    }

    public function export(ProductReturnFilterRequest $request, ProductReturnService $returns, CsvExportService $csv, ExcelExportService $excel): StreamedResponse
    {
        $rows = $returns->query($request->validated())->orderByDesc('created_at')->orderByDesc('id')->lazy(200)
            ->map(fn (ProductReturn $record): array => [
                $record->id, $record->created_at->format('Y-m-d H:i:s'), $record->product_snapshot['name'], $record->product_snapshot['sku'],
                $record->note_status === 'credited' ? 'Sudah Dikurangi' : 'Belum Dikurangi', $record->quantity,
                $record->product_snapshot['purchase_price'], $record->product_snapshot['selling_price'],
                $record->product_snapshot['kind'] === 'daily' ? 'Harian' : 'Non-Harian',
                $record->product_snapshot['brand'], $record->product_snapshot['color'], $record->product_snapshot['type'],
                $record->product_snapshot['distributors'], $record->status,
            ]);
        $headers = ['ID Retur', 'Tanggal', 'Produk', 'SKU', 'Status Nota', 'Jumlah Retur', 'Modal', 'Harga Jual', 'Tipe', 'Merk', 'Warna', 'Jenis Part', 'Distributor', 'Status Retur'];

        if ($request->query('format') === 'xlsx' || $request->query('format') === 'excel') {
            return $excel->download(
                'retur-produk-'.now()->format('Ymd').'.xlsx',
                'LAPORAN RETUR PRODUK',
                $headers,
                $rows,
                'Catatan Pengembalian Barang & Penyesuaian Nota'
            );
        }

        return $csv->download('retur-produk-'.now()->format('Ymd').'.csv', $headers, $rows);
    }
}
