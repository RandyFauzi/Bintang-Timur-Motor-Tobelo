<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'supplier_source_id')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->foreignId('supplier_source_id')->nullable()->constrained('products')->nullOnDelete();
            });
        }
        if (! Schema::hasColumn('purchase_order_items', 'product_id')) {
            Schema::table('purchase_order_items', function (Blueprint $table): void {
                $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            });
        }

        DB::transaction(function (): void {
            DB::table('products')->orderBy('id')->chunkById(100, function (Collection $products): void {
                foreach ($products as $product) {
                    $links = DB::table('product_distributors')->where('product_id', $product->id)->orderByDesc('is_primary')->orderBy('id')->get();
                    $seen = [];
                    foreach ($links as $link) {
                        if (isset($seen[$link->distributor_id])) {
                            DB::table('product_distributors')->where('id', $link->id)->delete();

                            continue;
                        }
                        if ($seen === []) {
                            $seen[$link->distributor_id] = true;
                            DB::table('product_distributors')->where('id', $link->id)->update(['is_primary' => true]);

                            continue;
                        }
                        $seen[$link->distributor_id] = true;
                        $attributes = (array) $product;
                        unset($attributes['id']);
                        $attributes['supplier_source_id'] = $product->id;
                        $attributes['sku'] = 'SUP-'.$product->id.'-'.$link->distributor_id;
                        $suffix = 0;
                        while (DB::table('products')->where('sku', $attributes['sku'])->exists()) {
                            $attributes['sku'] = 'SUP-'.$product->id.'-'.$link->distributor_id.'-'.++$suffix;
                        }
                        $attributes['barcode'] = null;
                        $attributes['purchase_price'] = (float) $link->purchase_price > 0 ? $link->purchase_price : $product->purchase_price;
                        $id = DB::table('products')->insertGetId($attributes);
                        DB::table('product_distributors')->where('id', $link->id)->update(['product_id' => $id, 'is_primary' => true]);
                        DB::table('product_stocks')->insert(['product_id' => $id, 'quantity' => 0, 'reserved_quantity' => 0, 'created_at' => now(), 'updated_at' => now()]);
                    }
                }
            });
            DB::table('purchase_order_items')->whereNull('product_id')->orderBy('id')->chunkById(100, function (Collection $items): void {
                foreach ($items as $item) {
                    $source = DB::table('products')->where('sku', $item->sku)->first();
                    $supplierId = DB::table('purchase_orders')->where('id', $item->purchase_order_id)->value('distributor_id');
                    if (! $source || ! $supplierId) {
                        continue;
                    }
                    $sourceId = $source->supplier_source_id ?? $source->id;
                    $productId = DB::table('products')->join('product_distributors', 'products.id', '=', 'product_distributors.product_id')
                        ->where(fn (Builder $query): Builder => $query->where('products.id', $sourceId)->orWhere('supplier_source_id', $sourceId))
                        ->where('distributor_id', $supplierId)->value('products.id');
                    if ($productId) {
                        DB::table('purchase_order_items')->where('id', $item->id)->update(['product_id' => $productId]);
                    }
                }
            });
        });
        if (! Schema::hasIndex('product_distributors', 'product_distributors_one_supplier_per_product')) {
            Schema::table('product_distributors', function (Blueprint $table): void {
                $table->unique('product_id', 'product_distributors_one_supplier_per_product');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_distributors', function (Blueprint $table): void {
            $table->dropUnique('product_distributors_one_supplier_per_product');
        });
        Schema::table('purchase_order_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('product_id');
        });
        Schema::table('products', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('supplier_source_id');
        });
    }
};
