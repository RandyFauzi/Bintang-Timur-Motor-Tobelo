<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductReturn;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductReturnService
{
    public function __construct(private InventoryService $inventory) {}

    /** @param array<string, mixed> $filters */
    public function query(array $filters): Builder
    {
        $query = ProductReturn::query();
        $status = ($filters['status'] ?? null) ?: 'active';
        if ($status !== 'all') {
            $query->where('status', $status);
        }
        if (! empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(fn (Builder $query) => $query->where('product_snapshot->name', 'like', $term)
                ->orWhere('product_snapshot->sku', 'like', $term));
        }
        foreach (['kind' => 'kind', 'note_status' => null] as $filter => $snapshot) {
            if (! empty($filters[$filter])) {
                $query->where($snapshot ? 'product_snapshot->'.$snapshot : $filter, $filters[$filter]);
            }
        }
        foreach (['color_ids' => 'color_id', 'brand_ids' => 'brand_id', 'type_ids' => 'type_id'] as $filter => $field) {
            if (! empty($filters[$filter])) {
                $query->whereIn('product_snapshot->'.$field, array_map('intval', $filters[$filter]));
            }
        }
        if (! empty($filters['distributor_ids'])) {
            $query->where(function (Builder $query) use ($filters): void {
                foreach ($filters['distributor_ids'] as $id) {
                    $query->orWhereJsonContains('product_snapshot->distributor_ids', (int) $id);
                }
            });
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{products: int, units: int, credited: int, pending: int}
     */
    public function summary(array $filters): array
    {
        $totals = $this->query($filters)->selectRaw('COUNT(DISTINCT product_id) as products, COALESCE(SUM(quantity), 0) as units, COALESCE(SUM(CASE WHEN note_status = ? THEN 1 ELSE 0 END), 0) as credited, COALESCE(SUM(CASE WHEN note_status = ? THEN 1 ELSE 0 END), 0) as pending', ['credited', 'pending'])->toBase()->first();

        return ['products' => (int) $totals->products, 'units' => (int) $totals->units, 'credited' => (int) $totals->credited, 'pending' => (int) $totals->pending];
    }

    /**
     * @param  array{request_id: string, product_id: int|string, quantity: int|string, note_status: string}  $data
     */
    public function create(array $data, User $actor): ProductReturn
    {
        $requestHash = hash('sha256', json_encode([$actor->id, (int) $data['product_id'], (int) $data['quantity'], $data['note_status']], JSON_THROW_ON_ERROR));
        try {
            return DB::transaction(function () use ($data, $actor, $requestHash): ProductReturn {
                $product = Product::query()->where('is_active', true)->lockForUpdate()->findOrFail($data['product_id']);
                $existing = ProductReturn::withTrashed()->where('request_id', $data['request_id'])->first();
                if ($existing) {
                    if (! hash_equals($existing->request_hash, $requestHash)) {
                        throw ValidationException::withMessages(['request_id' => 'Permintaan ini sudah digunakan. Muat ulang formulir.']);
                    }

                    return $existing;
                }

                $product->load(['color', 'brand', 'type', 'distributors']);
                $record = ProductReturn::create([
                    'request_id' => $data['request_id'], 'request_hash' => $requestHash, 'product_id' => $product->id, 'created_by' => $actor->id,
                    'quantity' => (int) $data['quantity'], 'note_status' => $data['note_status'], 'status' => 'active', 'version' => 1,
                    'product_snapshot' => [
                        'name' => $product->name, 'sku' => $product->sku, 'kind' => $product->product_kind,
                        'purchase_price' => $product->purchase_price, 'selling_price' => $product->selling_price,
                        'color_id' => $product->color_id, 'color' => $product->color?->name,
                        'brand_id' => $product->brand_id, 'brand' => $product->brand?->name,
                        'type_id' => $product->type_id, 'type' => $product->type?->name,
                        'distributor_ids' => $product->distributors->modelKeys(),
                        'distributors' => $product->distributors->pluck('name')->join(', '),
                    ],
                ]);
                $this->moveStock($record, $product, -$record->quantity, $actor);
                $this->log($record, $actor, 'created', null, -$record->quantity);

                return $record;
            }, attempts: 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['request_id' => 'Permintaan ini sudah digunakan. Muat ulang formulir.']);
        }
    }

    /**
     * @param  list<array{id: string, version: int|string}>  $selected
     * @param  array{quantity?: int|string|null, note_status?: string|null}  $changes
     */
    public function updateMany(array $selected, array $changes, User $actor): void
    {
        DB::transaction(function () use ($selected, $changes, $actor): void {
            $versions = collect($selected)->keyBy('id');
            $records = ProductReturn::whereKey($versions->keys()->all())->orderBy('id')->lockForUpdate()->get();
            if ($records->count() !== count($selected)) {
                throw ValidationException::withMessages(['selected' => 'Sebagian data tidak tersedia. Muat ulang halaman.']);
            }
            $products = Product::withTrashed()->whereKey($records->pluck('product_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($records as $record) {
                $this->assertVersion($record, (int) $versions[$record->id]['version']);
                if ($record->status !== 'active') {
                    throw ValidationException::withMessages(['selected' => 'Retur yang sudah dibatalkan tidak dapat diedit.']);
                }
                $before = $this->state($record);
                $quantity = isset($changes['quantity']) ? (int) $changes['quantity'] : $record->quantity;
                $delta = $record->quantity - $quantity;
                $noteStatus = $changes['note_status'] ?? $record->note_status;
                if ($delta === 0 && $noteStatus === $record->note_status) {
                    continue;
                }
                $this->moveStock($record, $products[$record->product_id], $delta, $actor);
                $record->fill(['quantity' => $quantity, 'note_status' => $noteStatus, 'version' => $record->version + 1])->save();
                $this->log($record, $actor, 'updated', $before, $delta);
            }
        }, attempts: 3);
    }

    public function cancel(ProductReturn $record, int $quantity, int $version, User $actor): void
    {
        DB::transaction(function () use ($record, $quantity, $version, $actor): void {
            $record = ProductReturn::lockForUpdate()->findOrFail($record->id);
            $this->assertVersion($record, $version);
            if ($record->status !== 'active' || $quantity > $record->quantity) {
                throw ValidationException::withMessages(['quantity' => 'Jumlah pembatalan melebihi sisa retur.']);
            }
            $before = $this->state($record);
            $product = Product::withTrashed()->lockForUpdate()->findOrFail($record->product_id);
            $this->moveStock($record, $product, $quantity, $actor);
            $record->quantity -= $quantity;
            $record->status = $record->quantity === 0 ? 'cancelled' : 'active';
            $record->version++;
            $record->save();
            $this->log($record, $actor, 'cancelled', $before, $quantity);
        }, attempts: 3);
    }

    public function delete(ProductReturn $record, int $version, User $actor): void
    {
        DB::transaction(function () use ($record, $version, $actor): void {
            $record = ProductReturn::lockForUpdate()->findOrFail($record->id);
            $this->assertVersion($record, $version);
            $before = $this->state($record);
            $quantity = $record->quantity;
            $product = Product::withTrashed()->lockForUpdate()->findOrFail($record->product_id);
            $this->moveStock($record, $product, $quantity, $actor);
            $record->fill(['quantity' => 0, 'status' => 'cancelled', 'version' => $record->version + 1])->save();
            $record->delete();
            $this->log($record, $actor, 'deleted', $before, $quantity);
        }, attempts: 3);
    }

    private function assertVersion(ProductReturn $record, int $version): void
    {
        if ($record->version !== $version) {
            throw ValidationException::withMessages(['version' => 'Data retur sudah berubah. Muat ulang halaman sebelum melanjutkan.']);
        }
    }

    private function moveStock(ProductReturn $record, Product $product, int $delta, User $actor): void
    {
        if ($delta === 0) {
            return;
        }
        $stock = $product->stock()->lockForUpdate()->first();
        $after = ($stock?->quantity ?? 0) + $delta;
        if ($after < ($stock?->reserved_quantity ?? 0) || $after > 2147483647) {
            throw ValidationException::withMessages(['quantity' => 'Stok tersedia tidak mencukupi atau jumlah stok melampaui batas. Periksa stok dan pesanan yang sudah dipesan.']);
        }
        $this->inventory->adjustStock($product, $delta, $delta < 0 ? 'supplier_return' : 'supplier_return_reversal', 'Retur produk #'.$record->id, $record, $actor->id);
    }

    /** @return array{quantity: int, note_status: string, status: string, version: int, deleted: bool} */
    private function state(ProductReturn $record): array
    {
        return ['quantity' => $record->quantity, 'note_status' => $record->note_status, 'status' => $record->status, 'version' => $record->version, 'deleted' => $record->trashed()];
    }

    /** @param array{quantity: int, note_status: string, status: string, version: int, deleted: bool}|null $before */
    private function log(ProductReturn $record, User $actor, string $action, ?array $before, int $delta): void
    {
        $record->events()->create(['user_id' => $actor->id, 'actor_name' => $actor->name, 'action' => $action, 'stock_delta' => $delta, 'before_state' => $before, 'after_state' => $this->state($record)]);
    }
}
