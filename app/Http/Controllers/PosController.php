<?php

namespace App\Http\Controllers;

use App\Http\Requests\CatalogueFilterRequest;
use App\Http\Requests\CheckoutRequest;
use App\Models\Product;
use App\Services\CatalogueService;
use App\Services\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PosController extends Controller
{
    public function index(CatalogueFilterRequest $request, CatalogueService $catalogue): View|JsonResponse
    {
        $products = $catalogue->products($request->validated())
            ->paginate((int) ($request->validated('per_page') ?? 6))->withQueryString();

        if ($request->wantsJson()) {
            return response()->json([
                'products' => $products->getCollection()->map(fn (Product $product): array => $this->productData($product))->values(),
                'next_page_url' => $products->nextPageUrl(),
                'has_more' => $products->hasMorePages(),
                'total' => $products->total(),
            ]);
        }

        return view('pos.index', array_merge($catalogue->options(), [
            'products' => $products,
            'catalogueProducts' => $products->getCollection()->map(fn (Product $product): array => $this->productData($product))->values(),
        ]));
    }

    public function getProductByBarcode(Request $request): JsonResponse
    {
        $data = $request->validate(['barcode' => ['required', 'string', 'max:100']]);
        $product = Product::with(['stock', 'brand', 'type', 'color', 'distributors'])
            ->where('is_active', true)->where('barcode', $data['barcode'])->first();
        if (! $product) {
            return response()->json(['success' => false, 'message' => 'Produk tidak ditemukan.'], 404);
        }

        return response()->json(['success' => true, 'data' => $this->productData($product)]);
    }

    public function checkout(CheckoutRequest $request, SaleService $saleService): JsonResponse
    {
        $sale = $saleService->processCheckout($request->validated(), $request->user()->id);

        return response()->json([
            'success' => true, 'message' => 'Transaksi berhasil dicatat.',
            'invoice' => $sale->invoice_number, 'change' => (float) $sale->change_amount,
            'total' => (float) $sale->grand_total, 'sold_at' => $sale->sold_at->format('d/m/Y H:i'),
            'detail_url' => route('transactions.show', $sale),
        ]);
    }

    /** @return array<string, mixed> */
    private function productData(Product $product): array
    {
        return [
            'id' => $product->id, 'name' => $product->name, 'sku' => $product->sku, 'barcode' => $product->barcode,
            'price' => (float) $product->selling_price,
            'available' => max(0, ($product->stock?->quantity ?? 0) - ($product->stock?->reserved_quantity ?? 0)),
            'minimum' => $product->stock_minimum, 'brand' => $product->brand?->name,
            'color' => $product->color?->name, 'type' => $product->type?->name,
            'distributor' => $product->distributors->first()?->name,
        ];
    }
}
