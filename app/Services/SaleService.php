<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;

class SaleService
{
    protected InventoryService $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    /**
     * Process checkout
     * 
     * $data expected format:
     * [
     *    'customer_name' => '...',
     *    'customer_phone' => '...',
     *    'discount' => 0,
     *    'tax' => 0,
     *    'payment_method' => 'cash',
     *    'paid_amount' => 150000,
     *    'items' => [
     *        ['product_id' => 1, 'quantity' => 2, 'discount' => 0],
     *        ...
     *    ]
     * ]
     */
    public function processCheckout(array $data, int $userId)
    {
        return DB::transaction(function () use ($data, $userId) {
            
            // 1. Calculate totals & prepare items
            $subtotal = 0;
            $saleItemsData = [];
            
            foreach ($data['items'] as $itemData) {
                // We don't lock here yet, we lock in inventory service
                $product = Product::findOrFail($itemData['product_id']);
                
                if (!$product->is_active) {
                    throw new Exception("Produk {$product->name} sedang tidak aktif.");
                }
                
                $qty = (int) $itemData['quantity'];
                $itemDiscount = (float) ($itemData['discount'] ?? 0);
                $unitPrice = $product->selling_price;
                $itemSubtotal = ($unitPrice * $qty) - $itemDiscount;
                
                $subtotal += $itemSubtotal;
                
                $saleItemsData[] = [
                    'product' => $product,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'discount' => $itemDiscount,
                    'subtotal' => $itemSubtotal
                ];
            }
            
            $globalDiscount = (float) ($data['discount'] ?? 0);
            $tax = (float) ($data['tax'] ?? 0);
            $grandTotal = $subtotal - $globalDiscount + $tax;
            $paidAmount = (float) ($data['paid_amount'] ?? 0);
            
            if ($paidAmount < $grandTotal) {
                throw new Exception("Jumlah pembayaran (Rp " . number_format($paidAmount, 0, ',', '.') . ") kurang dari Total Belanja (Rp " . number_format($grandTotal, 0, ',', '.') . ")");
            }
            
            $changeAmount = $paidAmount - $grandTotal;
            
            // 2. Create Sale (Header)
            $sale = new Sale();
            $sale->invoice_number = $this->generateInvoiceNumber();
            $sale->user_id = $userId;
            $sale->subtotal = $subtotal;
            $sale->discount = $globalDiscount;
            $sale->tax = $tax;
            $sale->grand_total = $grandTotal;
            $sale->payment_status = 'paid';
            $sale->status = 'completed';
            $sale->customer_name = $data['customer_name'] ?? null;
            $sale->customer_phone = $data['customer_phone'] ?? null;
            $sale->paid_amount = $paidAmount;
            $sale->change_amount = $changeAmount;
            $sale->sold_at = now();
            $sale->save();
            
            // 3. Create Sale Items & Decrease Stock
            foreach ($saleItemsData as $item) {
                $product = $item['product'];
                
                $saleItem = new SaleItem();
                $saleItem->sale_id = $sale->id;
                $saleItem->product_id = $product->id;
                $saleItem->product_name = $product->name;
                $saleItem->sku = $product->sku;
                $saleItem->barcode = $product->barcode;
                $saleItem->quantity = $item['quantity'];
                $saleItem->unit_price = $item['unit_price'];
                $saleItem->discount = $item['discount'];
                $saleItem->subtotal = $item['subtotal'];
                $saleItem->save();
                
                // Decrease stock via InventoryService (handles pessimistic locking)
                $this->inventoryService->adjustStock(
                    $product,
                    -$item['quantity'],
                    'sale',
                    "Penjualan #{$sale->invoice_number}",
                    $saleItem,
                    $userId
                );
            }
            
            // 4. Create Payment
            $payment = new Payment();
            $payment->sale_id = $sale->id;
            $payment->method = $data['payment_method'] ?? 'cash';
            $payment->amount = $grandTotal; // Assuming full payment applied to this transaction
            $payment->paid_at = now();
            $payment->save();
            
            return $sale;
        });
    }

    private function generateInvoiceNumber(): string
    {
        $date = now()->format('Ymd');
        $prefix = "INV-{$date}-";
        
        $lastSale = Sale::where('invoice_number', 'like', "{$prefix}%")
            ->orderBy('invoice_number', 'desc')
            ->first();
            
        if (!$lastSale) {
            return $prefix . '0001';
        }
        
        $lastNumber = (int) substr($lastSale->invoice_number, -4);
        $newNumber = str_pad((string)($lastNumber + 1), 4, '0', STR_PAD_LEFT);
        
        return $prefix . $newNumber;
    }
}
