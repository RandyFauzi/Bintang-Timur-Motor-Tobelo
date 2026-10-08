<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Exception;

class InventoryService
{
    /**
     * Update stock and record movement
     */
    public function adjustStock(Product $product, int $quantity, string $type, string $note = null, $reference = null, $userId = null)
    {
        return DB::transaction(function () use ($product, $quantity, $type, $note, $reference, $userId) {
            // Lock the stock row for update to prevent race conditions
            $stock = ProductStock::firstOrCreate(
                ['product_id' => $product->id],
                ['quantity' => 0, 'reserved_quantity' => 0]
            );
            
            // Lock for update
            $stock = ProductStock::where('id', $stock->id)->lockForUpdate()->first();
            
            $stockBefore = $stock->quantity;
            $stockAfter = $stockBefore + $quantity;
            
            if ($type === 'sale' && $stockAfter < 0) {
                throw new Exception("Stok tidak mencukupi untuk produk: {$product->name}. Sisa stok: {$stockBefore}");
            }
            
            $stock->quantity = $stockAfter;
            $stock->save();
            
            // Record movement
            $movement = new StockMovement([
                'product_id' => $product->id,
                'type' => $type,
                'quantity' => $quantity,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'note' => $note,
                'created_by' => $userId ?? auth()->id(),
            ]);
            
            if ($reference) {
                $movement->reference_type = get_class($reference);
                $movement->reference_id = $reference->id;
            }
            
            $movement->save();
            
            return $movement;
        });
    }

    /**
     * Smart Stock Alert: Get products with low stock
     */
    public function getLowStockProducts()
    {
        return Product::where('is_active', true)
            ->whereHas('stock', function ($query) {
                // we compare stock.quantity <= product.stock_minimum
                // In Eloquent, comparing two columns from different tables requires a join or raw query
            })
            ->join('product_stocks', 'products.id', '=', 'product_stocks.product_id')
            ->whereRaw('product_stocks.quantity <= products.stock_minimum')
            ->select('products.*', 'product_stocks.quantity as current_stock')
            ->get();
    }
}
