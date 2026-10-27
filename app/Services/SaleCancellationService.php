<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleCancellationService
{
    public function __construct(private InventoryService $inventory) {}

    public function cancel(Sale $sale, string $reason, User $actor): Sale
    {
        return DB::transaction(function () use ($sale, $reason, $actor): Sale {
            $sale = Sale::lockForUpdate()->findOrFail($sale->id);

            if ($sale->status !== 'completed') {
                throw ValidationException::withMessages([
                    'status' => 'Hanya transaksi berstatus selesai yang dapat dibatalkan.',
                ]);
            }

            $sale->load(['items.product' => fn ($query) => $query->withTrashed()]);

            $before = [
                'status' => $sale->status,
                'total' => (string) $sale->grand_total,
                'subtotal' => (string) $sale->subtotal,
                'payment_status' => $sale->payment_status,
                'items' => $sale->items->map->only([
                    'id', 'product_id', 'product_name', 'sku', 'quantity', 'unit_price', 'discount', 'subtotal',
                ])->all(),
            ];

            foreach ($sale->items as $item) {
                if ($item->product_id) {
                    $product = Product::withTrashed()->lockForUpdate()->find($item->product_id);
                    if ($product) {
                        $this->inventory->adjustStock(
                            $product,
                            (int) $item->quantity,
                            'sale_cancellation',
                            "Pembatalan #{$sale->invoice_number}: {$reason}",
                            $sale,
                            $actor->id
                        );
                    }
                }
            }

            $sale->status = 'cancelled';
            $sale->cancelled_by = $actor->id;
            $sale->cancelled_at = now();
            $sale->cancellation_reason = $reason;
            $sale->version++;
            $sale->save();

            $after = [
                'status' => $sale->status,
                'total' => (string) $sale->grand_total,
                'subtotal' => (string) $sale->subtotal,
                'payment_status' => $sale->payment_status,
                'cancelled_by' => $actor->id,
                'cancelled_at' => $sale->cancelled_at->toISOString(),
                'cancellation_reason' => $reason,
            ];

            $sale->corrections()->create([
                'user_id' => $actor->id,
                'actor_name' => $actor->name,
                'note' => "Pembatalan transaksi: {$reason}",
                'before_state' => $before,
                'after_state' => $after,
            ]);

            return $sale;
        }, attempts: 3);
    }
}
