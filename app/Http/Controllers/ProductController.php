<?php
namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Brand;
use App\Models\ProductType;
use App\Models\Color;
use Illuminate\Http\Request;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with(['brand', 'type', 'stock'])->where('is_active', true);
        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('sku', 'like', "%{$request->search}%")
                  ->orWhere('barcode', 'like', "%{$request->search}%");
            });
        }
        $products = $query->orderBy('id', 'desc')->paginate(15);
        return view('products.index', compact('products'));
    }

    public function create()
    {
        $brands = Brand::all();
        $types = ProductType::all();
        $colors = Color::all();
        return view('products.create', compact('brands', 'types', 'colors'));
    }

    public function store(Request $request, InventoryService $inventoryService)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'required|string|unique:products,sku',
            'barcode' => 'nullable|string|unique:products,barcode',
            'brand_id' => 'nullable|exists:brands,id',
            'type_id' => 'nullable|exists:product_types,id',
            'purchase_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'stock_minimum' => 'required|integer|min:0',
            'initial_stock' => 'required|integer|min:0',
        ]);

        DB::transaction(function () use ($validated, $inventoryService) {
            $product = Product::create($validated);
            if ($validated['initial_stock'] > 0) {
                $inventoryService->adjustStock($product, $validated['initial_stock'], 'initial', 'Stok Awal dari Sistem');
            }
        });

        return redirect()->route('products.index')->with('success', 'Produk berhasil ditambahkan.');
    }
}