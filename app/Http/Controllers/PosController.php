<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Services\SaleService;
use Exception;

class PosController extends Controller
{
    public function index()
    {
        // Load active products with stock
        $products = Product::with('stock')->where('is_active', true)->get();
        return view('pos.index', compact('products'));
    }

    public function getProductByBarcode(Request $request)
    {
        $product = Product::with('stock')
            ->where('is_active', true)
            ->where('barcode', $request->barcode)
            ->first();
            
        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Produk tidak ditemukan'], 404);
        }
        return response()->json(['success' => true, 'data' => $product]);
    }

    public function checkout(Request $request, SaleService $saleService)
    {
        try {
            $data = $request->validate([
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.quantity' => 'required|integer|min:1',
                'paid_amount' => 'required|numeric|min:0',
                'payment_method' => 'required|string',
            ]);
            
            $sale = $saleService->processCheckout($data, auth()->id());
            
            return response()->json([
                'success' => true, 
                'message' => 'Transaksi Berhasil', 
                'invoice' => $sale->invoice_number,
                'change' => $sale->change_amount
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}