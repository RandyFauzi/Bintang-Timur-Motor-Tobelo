<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleCorrectionService
{
    public function __construct(private InventoryService $inventory) {}

    /** @param array{version: int|string, items: list<array{item_id?: ?string, product_id: int|string, quantity: int|string}>, note?: ?string} $data */
    public function update(Sale $sale, array $data, User $actor): void
    {
        DB::transaction(function () use ($sale, $data, $actor): void {
            $sale = Sale::lockForUpdate()->findOrFail($sale->id);
            if ($sale->status !== 'completed' || (int) $sale->version !== (int) $data['version']) {
                throw ValidationException::withMessages(['version' => 'Transaksi sudah berubah atau dibatalkan. Muat ulang sebelum mengedit.']);
            }
            $sale->load('items', 'payments');
            if ($sale->items->contains(fn (SaleItem $item): bool => $item->product_id === null)) {
                throw ValidationException::withMessages(['items' => 'Produk asal sudah dihapus permanen sehingga transaksi tidak dapat dikoreksi.']);
            }
            $before = $this->snapshot($sale);
            $oldItems = $sale->items->keyBy('id');
            $ids = $sale->items->pluck('product_id')->merge(collect($data['items'])->pluck('product_id'))->filter()->unique();
            $products = Product::withTrashed()->whereKey($ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $oldQuantities = $sale->items->groupBy('product_id')->map(fn ($items): int => (int) $items->sum('quantity'));
            $newQuantities = collect($data['items'])->groupBy('product_id')->map(fn ($items): int => (int) $items->sum('quantity'));
            $prepared = [];
            $subtotal = 0;
            foreach ($data['items'] as $row) {
                $old = empty($row['item_id']) ? null : $oldItems->get($row['item_id']);
                $product = $products->get((int) $row['product_id']);
                if ((! empty($row['item_id']) && (! $old || $old->product_id !== (int) $row['product_id']))
                    || ! $product || (! $old && ($product->trashed() || ! $product->is_active))) {
                    throw ValidationException::withMessages(['items' => 'Produk pada transaksi tidak valid atau sudah tidak tersedia.']);
                }
                $quantity = (int) $row['quantity'];
                $price = $this->cents($old?->unit_price ?? $product->selling_price);
                $discount = $old ? (int) round($this->cents($old->discount) / max(1, $old->quantity) * $quantity) : 0;
                $lineTotal = $price * $quantity - $discount;
                if ($lineTotal < 0 || $lineTotal > 999999999999999) {
                    throw ValidationException::withMessages(['items' => 'Subtotal produk melampaui batas yang diperbolehkan.']);
                }
                $subtotal += $lineTotal;
                $prepared[] = ['old' => $old, 'product' => $product, 'quantity' => $quantity, 'unit_price' => $price / 100, 'discount' => $discount / 100, 'subtotal' => $lineTotal / 100];
            }
            $total = $subtotal - $this->cents($sale->discount) + $this->cents($sale->tax);
            if ($total < 0 || $total > 999999999999999) {
                throw ValidationException::withMessages(['items' => 'Total transaksi tidak valid. Periksa diskon dan jumlah produk.']);
            }
            $retained = [];
            foreach ($prepared as $row) {
                $item = $row['old'] ?? new SaleItem(['sale_id' => $sale->id, 'product_id' => $row['product']->id, 'product_name' => $row['product']->name, 'sku' => $row['product']->sku, 'barcode' => $row['product']->barcode]);
                $item->fill(collect($row)->only(['quantity', 'unit_price', 'discount', 'subtotal'])->all())->save();
                $retained[] = $item->id;
            }
            foreach ($ids->sort() as $id) {
                $delta = (int) $oldQuantities->get($id, 0) - (int) $newQuantities->get($id, 0);
                if ($delta === 0) {
                    continue;
                }
                $product = $products->get($id);
                $stock = $product?->stock()->lockForUpdate()->first();
                $after = ($stock?->quantity ?? 0) + $delta;
                if (! $product || $after < ($stock?->reserved_quantity ?? 0) || $after > 2147483647) {
                    throw ValidationException::withMessages(['items' => 'Stok tersedia tidak mencukupi untuk perubahan ini.']);
                }
                $this->inventory->adjustStock($product, $delta, 'sale_correction', 'Koreksi '.$sale->invoice_number, $sale, $actor->id);
            }
            $sale->items()->whereNotIn('id', $retained)->delete();
            $paid = $sale->payments->isEmpty()
                ? $this->cents($sale->paid_amount) - $this->cents($sale->change_amount)
                : $this->cents($sale->payments->sum('amount'));
            $sale->subtotal = $subtotal / 100;
            $sale->grand_total = $total / 100;
            $sale->payment_status = $paid >= $total ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid');
            $sale->version++;
            $sale->save();
            $sale->load('items');
            $sale->corrections()->create(['user_id' => $actor->id, 'actor_name' => $actor->name, 'note' => $data['note'] ?? null, 'before_state' => $before, 'after_state' => $this->snapshot($sale)]);
        }, attempts: 3);
    }

    private function cents(float|int|string $value): int
    {
        return (int) round((float) $value * 100);
    }

    /** @return array{total: string, subtotal: string, payment_status: string, items: array} */
    private function snapshot(Sale $sale): array
    {
        return ['total' => (string) $sale->grand_total, 'subtotal' => (string) $sale->subtotal, 'payment_status' => $sale->payment_status, 'items' => $sale->items->map->only(['id', 'product_id', 'product_name', 'sku', 'quantity', 'unit_price', 'discount', 'subtotal'])->all()];
    }
}
