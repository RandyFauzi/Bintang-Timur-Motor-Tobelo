<?php

namespace App\Services;

use App\Models\Distributor;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SupplierProductService
{
    private ?bool $schemaReady = null;

    public function ensureSchemaReady(): void
    {
        $this->schemaReady ??= Schema::hasColumn('products', 'supplier_source_id') && Schema::hasColumn('purchase_order_items', 'product_id');
        if (! $this->schemaReady) {
            throw ValidationException::withMessages(['items' => 'Pembaruan database produk belum diterapkan. Minta administrator menjalankan migrasi sebelum memfinalisasi PO.']);
        }
    }

    public function forDistributor(Product $product, Distributor $distributor, float $estimatedCost): Product
    {
        $this->ensureSchemaReady();

        return DB::transaction(function () use ($product, $distributor, $estimatedCost): Product {
            $source = Product::withTrashed()->lockForUpdate()->findOrFail($product->supplier_source_id ?? $product->id);
            $variant = Product::withTrashed()->where(fn (Builder $query): Builder => $query->whereKey($source->id)->orWhere('supplier_source_id', $source->id))
                ->whereHas('distributors', fn (Builder $query): Builder => $query->where('distributors.id', $distributor->id))->first();
            if ($variant) {
                if ($variant->trashed() || ! $variant->is_active) {
                    throw ValidationException::withMessages(['items' => 'Produk distributor ini sudah diarsipkan atau nonaktif. Aktifkan produk terlebih dahulu.']);
                }

                return $variant;
            }
            if (! $source->trashed() && $source->is_active && ! $source->distributors()->exists()) {
                $source->distributors()->attach($distributor->id, ['is_primary' => true, 'purchase_price' => $source->purchase_price]);

                return $source;
            }
            $variant = $product->replicate(['sku', 'barcode', 'supplier_source_id', 'deleted_at']);
            $variant->sku = 'SUP-'.$source->id.'-'.$distributor->id.'-'.strtolower((string) Str::ulid());
            $variant->barcode = null;
            $variant->supplier_source_id = $source->id;
            $variant->purchase_price = $estimatedCost;
            $variant->save();
            $variant->distributors()->attach($distributor->id, ['is_primary' => true, 'purchase_price' => $estimatedCost]);
            $variant->stock()->create(['quantity' => 0, 'reserved_quantity' => 0]);

            return $variant;
        });
    }
}
