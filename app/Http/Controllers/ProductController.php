<?php

namespace App\Http\Controllers;

use App\Http\Requests\CatalogueFilterRequest;
use App\Http\Requests\ProductRequest;
use App\Models\Product;
use App\Services\CatalogueService;
use App\Services\CsvExportService;
use App\Services\DashboardService;
use App\Services\ExcelExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductController extends Controller
{
    public function index(CatalogueFilterRequest $request, CatalogueService $catalogue, DashboardService $dashboard): View
    {
        $filters = $request->validated();
        $products = $catalogue->products($filters)->paginate((int) ($filters['per_page'] ?? ($request->input('view') === 'cards' ? 6 : 15)))->withQueryString();
        $editing = $request->filled('edit') ? Product::with(['stock', 'brand', 'type', 'color', 'distributors'])->findOrFail($request->integer('edit')) : null;

        return view('products.index', array_merge($catalogue->options(), ['products' => $products, 'editing' => $editing, 'categories' => $catalogue->categories(), 'stockOverview' => $dashboard->stockOverview()]));
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('products.index', ['create' => 1]);
    }

    public function edit(Product $product): RedirectResponse
    {
        return redirect()->route('products.index', ['edit' => $product->id]);
    }

    public function show(Product $product): RedirectResponse
    {
        return redirect()->route('products.index', ['edit' => $product->id]);
    }

    public function store(ProductRequest $request, CatalogueService $catalogue): RedirectResponse
    {
        $catalogue->saveProduct($request->validated(), $request->user()->id);

        return redirect()->route('products.index')->with('success', 'Produk baru berhasil ditambahkan.');
    }

    public function update(ProductRequest $request, Product $product, CatalogueService $catalogue): RedirectResponse
    {
        $catalogue->saveProduct($request->validated(), $request->user()->id, $product);

        return redirect()->route('products.index')->with('success', $product->name.' berhasil diperbarui.');
    }

    public function destroy(Product $product, CatalogueService $catalogue): RedirectResponse
    {
        $catalogue->deleteProduct($product);

        return redirect()->route('products.index', ['view' => 'cards'])->with('success', 'Produk berhasil dihapus dari katalog. Riwayat tetap tersimpan.');
    }

    public function export(CatalogueFilterRequest $request, CatalogueService $catalogue, CsvExportService $csv, ExcelExportService $excel): StreamedResponse
    {
        $rows = $catalogue->products($request->validated())->lazy(200)->map(fn (Product $product): array => [
            $product->name,
            $product->sku,
            $product->color?->name,
            $product->purchase_price,
            $product->selling_price,
            $product->product_kind === 'daily' ? 'Harian' : 'Non-Harian',
            $product->brand?->name,
            $product->type?->name,
            $product->distributors->pluck('name')->join(', '),
            $product->stock?->quantity ?? 0,
        ]);
        $headers = ['Nama Produk', 'SKU', 'Warna', 'Modal', 'Harga Jual', 'Tipe Produk', 'Merk', 'Jenis Part', 'Distributor', 'Stok'];

        if ($request->query('format') === 'xlsx' || $request->query('format') === 'excel') {
            return $excel->download(
                'produk-'.now()->format('Ymd').'.xlsx',
                'DAFTAR PRODUK & STOK BENGKEL',
                $headers,
                $rows,
                'Katalog Suku Cadang & Inventaris Toko Bintang Timur Motor'
            );
        }

        return $csv->download('produk-'.now()->format('Ymd').'.csv', $headers, $rows);
    }
}
