<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Color;
use App\Models\Distributor;
use App\Models\Product;
use App\Models\ProductType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CatalogueService
{
    public function __construct(private InventoryService $inventoryService) {}

    /** @param array<string, mixed> $filters */
    public function products(array $filters): Builder
    {
        $query = Product::query()->with(['brand', 'type', 'color', 'stock', 'distributors'])->where('is_active', true);
        if (! empty($filters['search'])) {
            $words = preg_split('/\s+/u', trim($filters['search']), -1, PREG_SPLIT_NO_EMPTY);
            foreach ($words as $word) {
                $term = '%'.$word.'%';
                $normalized = preg_replace('/[\s_-]+/u', '', mb_strtolower($word));
                $kinds = array_keys(array_filter(Product::KINDS, fn (string $label, string $key): bool => str_contains(preg_replace('/[\s_-]+/u', '', mb_strtolower($label.' '.$key)), $normalized), ARRAY_FILTER_USE_BOTH));
                $query->where(function (Builder $relatedQuery) use ($term, $kinds): void {
                    $relatedQuery->where('name', 'like', $term)->orWhere('sku', 'like', $term)->orWhere('barcode', 'like', $term);
                    foreach (['brand', 'type', 'color', 'distributors'] as $relation) {
                        $relatedQuery->orWhereHas($relation, fn (Builder $relationQuery) => $relationQuery->where('name', 'like', $term));
                    }
                    if ($kinds !== []) {
                        $relatedQuery->orWhereIn('product_kind', $kinds);
                    }
                });
            }
        }
        if (! empty($filters['kind'])) {
            $query->where('product_kind', $filters['kind']);
        }
        foreach (['brand_ids' => 'brand_id', 'type_ids' => 'type_id', 'color_ids' => 'color_id'] as $field => $column) {
            if (! empty($filters[$field])) {
                $query->whereIn($column, $filters[$field]);
            }
        }
        if (! empty($filters['distributor_ids'])) {
            $query->whereHas('distributors', fn (Builder $relatedQuery) => $relatedQuery->whereIn('distributors.id', $filters['distributor_ids']));
        }
        if (! empty($filters['low_stock'])) {
            $query->where(fn (Builder $relatedQuery) => $relatedQuery->whereDoesntHave('stock')->orWhereHas('stock', fn (Builder $stockQuery) => $stockQuery->whereColumn('quantity', '<=', 'products.stock_minimum')));
        }

        return $query->orderByDesc('products.id');
    }

    /** @return array<string, mixed> */
    public function options(): array
    {
        return ['brands' => Brand::where('is_active', true)->orderBy('name')->get(), 'types' => ProductType::where('is_active', true)->orderBy('name')->get(), 'colors' => Color::where('is_active', true)->orderBy('name')->get(), 'distributors' => Distributor::where('is_active', true)->orderBy('name')->get()];
    }

    /** @param array<string, mixed> $data */
    public function saveProduct(array $data, int $userId, ?Product $product = null, bool $replaceSku = false): Product
    {
        return DB::transaction(function () use ($data, $userId, $product, $replaceSku): Product {
            $creating = $product === null;
            $attributes = Arr::only($data, ['name', 'sku', 'barcode', 'purchase_price', 'selling_price', 'stock_minimum', 'product_kind', 'brand_id', 'type_id', 'color_id']);
            foreach (['brand' => Brand::class, 'type' => ProductType::class, 'color' => Color::class] as $field => $model) {
                if (array_key_exists($field.'_name', $data)) {
                    if (empty($data[$field.'_name'])) {
                        $attributes[$field.'_id'] = null;

                        continue;
                    }
                    $name = trim($data[$field.'_name']);
                    $defaults = $field === 'color' ? [] : ['slug' => Str::slug($name).'-'.Str::lower((string) Str::ulid())];
                    $attributes[$field.'_id'] = $model::firstOrCreate(['name' => $name], $defaults)->id;
                }
            }
            if ($creating) {
                $attributes['sku'] = $attributes['sku'] ?? 'PRD-'.Str::ulid();
                $product = Product::create($attributes);
            } else {
                if (! $replaceSku || empty($attributes['sku'])) {
                    unset($attributes['sku']);
                }
                $product->fill($attributes)->save();
            }
            $stock = $product->stock()->firstOrCreate([], ['quantity' => 0, 'reserved_quantity' => 0]);
            $stock = $product->stock()->whereKey($stock->id)->lockForUpdate()->firstOrFail();
            $quantity = (int) ($data['quantity'] ?? $data['initial_stock'] ?? $stock->quantity);
            if ($quantity < $stock->reserved_quantity) {
                throw ValidationException::withMessages(['quantity' => 'Jumlah stok tidak boleh kurang dari stok yang sudah dipesan.']);
            }
            $difference = $quantity - $stock->quantity;
            if ($difference !== 0) {
                $this->inventoryService->adjustStock($product, $difference, $creating ? 'initial' : 'adjustment', $creating ? 'Stok awal produk' : 'Penyesuaian stok produk', null, $userId);
            }
            if (array_key_exists('distributor_ids', $data)) {
                $product->distributors()->sync($data['distributor_ids']);
            }

            return $product;
        });
    }

    /** @param list<int|string> $ids
     * @param  array<string, mixed>  $changes
     */
    public function bulkProducts(array $ids, array $changes, int $userId, bool $deleting = false): void
    {
        DB::transaction(function () use ($ids, $changes, $userId, $deleting): void {
            $products = Product::whereKey($ids)->where('is_active', true)->orderBy('id')->lockForUpdate()->get();
            if ($products->count() !== count($ids)) {
                throw ValidationException::withMessages(['selected' => 'Sebagian produk tidak tersedia. Muat ulang halaman.']);
            }
            foreach ($products as $product) {
                if ($deleting) {
                    $this->deleteProduct($product);
                } else {
                    $this->saveProduct($changes, $userId, $product);
                }
            }
        });
    }

    public function deleteProduct(Product $product): void
    {
        DB::transaction(function () use ($product): void {
            $product = Product::query()->lockForUpdate()->findOrFail($product->id);
            $stock = $product->stock()->lockForUpdate()->first();
            if (($stock?->reserved_quantity ?? 0) > 0) {
                throw ValidationException::withMessages(['product' => 'Produk masih memiliki stok yang dipesan dan belum dapat dihapus.']);
            }

            $product->delete();
        });
    }

    /** @return array<int, array{name: string, value: int}> */
    public function categories(): array
    {
        return ProductType::query()->withCount(['products' => fn (Builder $relatedQuery) => $relatedQuery->where('is_active', true)])->orderBy('name')->get()->map(fn (ProductType $type): array => ['name' => $type->name, 'value' => $type->products_count])->all();
    }

    /** @param array<string, mixed> $filters */
    public function distributorQuery(array $filters): Builder
    {
        $query = Distributor::query()->where('is_active', true)->withCount('products');
        if (! empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(fn (Builder $relatedQuery) => $relatedQuery->where('name', 'like', $term)->orWhere('company_name', 'like', $term)->orWhere('phone', 'like', $term));
        }
        if (! empty($filters['type_ids'])) {
            $query->whereHas('products', fn (Builder $relatedQuery) => $relatedQuery->whereIn('type_id', $filters['type_ids']));
        }
        if (! empty($filters['has_products'])) {
            $query->has('products');
        }

        return $query->orderBy('name')->orderBy('id');
    }

    /** @param list<int> $ids @return array<int, array<int, object>> */
    public function distributorCategories(array $ids): array
    {
        return DB::table('product_distributors')->join('products', 'products.id', '=', 'product_distributors.product_id')
            ->join('product_types', 'product_types.id', '=', 'products.type_id')->whereNull('products.deleted_at')->whereNull('product_types.deleted_at')
            ->whereIn('distributor_id', $ids)->select('distributor_id', 'product_types.name')->selectRaw('COUNT(DISTINCT products.id) as value')
            ->groupBy('distributor_id', 'product_types.id', 'product_types.name')->orderBy('product_types.name')->get()->groupBy('distributor_id')->map->all()->all();
    }
}
