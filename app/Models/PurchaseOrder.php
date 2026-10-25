<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PurchaseOrder extends Model
{
    use HasFactory;

    protected $fillable = ['request_id', 'reference', 'distributor_id', 'distributor_name', 'ordered_on', 'expected_on', 'notes', 'purchase_plan_id', 'package_count', 'shipping_cost_cents'];

    protected function casts(): array
    {
        return ['ordered_on' => 'date', 'expected_on' => 'date', 'version' => 'integer', 'note_issued' => 'boolean', 'supplier_note_on' => 'date', 'supplier_note_total_cents' => 'integer', 'package_count' => 'integer', 'shipping_cost_cents' => 'integer', 'shipment_finalized_at' => 'datetime', 'archived_at' => 'datetime'];
    }

    public function shippingCost(): float
    {
        return ($this->shipping_cost_cents ?? 0) / 100;
    }

    public function purchasePlan(): BelongsTo
    {
        return $this->belongsTo(PurchasePlan::class);
    }

    public function scopeWithReceiptStatus(Builder $query, string $status, string $relation = 'items'): void
    {
        if ($status === 'pending') {
            $query->whereDoesntHave($relation, fn (Builder $items): Builder => $items->whereNotNull('actual_quantity'));
        } elseif ($status === 'checking') {
            $query->whereHas($relation, fn (Builder $items): Builder => $items->whereNull('actual_quantity'))
                ->whereHas($relation, fn (Builder $items): Builder => $items->whereNotNull('actual_quantity'));
        } elseif (in_array($status, ['discrepancy', 'matched'], true)) {
            $query->whereHas($relation)->whereDoesntHave($relation, fn (Builder $items): Builder => $items->whereNull('actual_quantity'));
            $difference = fn (Builder $items): Builder => $items->whereColumn('actual_quantity', '!=', 'quantity');
            if ($status === 'discrepancy') {
                $query->whereHas($relation, $difference);
            } else {
                $query->whereDoesntHave($relation, $difference);
            }
        }
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(DistributorInvoice::class);
    }

    public function statusLabel(): string
    {
        if ($this->items->every(fn (PurchaseOrderItem $item): bool => $item->actual_quantity === null)) {
            return 'In Progress';
        }
        if ($this->items->contains(fn (PurchaseOrderItem $item): bool => $item->actual_quantity === null)) {
            return 'Sedang diperiksa';
        }

        return $this->items->contains(fn (PurchaseOrderItem $item): bool => $item->actual_quantity !== $item->quantity) ? 'Ada selisih' : 'Selesai';
    }

    public function total(): float
    {
        return $this->items->sum(fn (PurchaseOrderItem $item): float => $item->quantity * (float) $item->unit_price);
    }
}
