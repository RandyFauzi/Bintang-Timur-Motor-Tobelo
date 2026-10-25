<?php

namespace App\Models;

use Database\Factories\PurchasePlanFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchasePlan extends Model
{
    /** @use HasFactory<PurchasePlanFactory> */
    use HasFactory;

    protected $fillable = ['request_id', 'reference', 'name', 'planned_on', 'items', 'status', 'version', 'finalized_at'];

    protected function casts(): array
    {
        return ['planned_on' => 'date', 'items' => 'array', 'version' => 'integer', 'finalized_at' => 'datetime'];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function statusLabel(): string
    {
        $items = $this->orders->flatMap(fn (PurchaseOrder $order): Collection => $order->items);
        if ($items->every(fn (PurchaseOrderItem $item): bool => $item->actual_quantity === null)) {
            return 'In Progress';
        }
        if ($items->contains(fn (PurchaseOrderItem $item): bool => $item->actual_quantity === null)) {
            return 'Sedang diperiksa';
        }

        return $items->contains(fn (PurchaseOrderItem $item): bool => $item->actual_quantity !== $item->quantity) ? 'Ada selisih' : 'Selesai';
    }
}
