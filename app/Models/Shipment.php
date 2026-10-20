<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ShipmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shipment extends Model
{
    /** @use HasFactory<ShipmentFactory> */
    use HasFactory;

    public const STATUSES = ['scheduled' => 'Terjadwal', 'in_transit' => 'Sedang Berjalan', 'near_arrival' => 'Sudah Dekat Sampai', 'delayed' => 'Pengiriman Terlambat', 'delivered' => 'Sudah Sampai'];

    protected $fillable = ['reference', 'shipping_service_id', 'service_name', 'duration_days', 'cargo_name', 'vessel_name', 'origin_port', 'destination_port', 'destination_address', 'package_count', 'departed_on', 'estimated_arrival_on', 'notes'];

    protected function casts(): array
    {
        return ['departed_on' => 'immutable_date', 'estimated_arrival_on' => 'immutable_date', 'delivered_at' => 'immutable_datetime', 'duration_days' => 'integer', 'package_count' => 'integer', 'version' => 'integer'];
    }

    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now(config('shipping.timezone'))->startOfDay();
    }

    public function status(): string
    {
        if ($this->delivered_at !== null) {
            return 'delivered';
        }
        $today = self::today()->toDateString();
        if ($this->departed_on->toDateString() > $today) {
            return 'scheduled';
        }
        if ($this->estimated_arrival_on->toDateString() < $today) {
            return 'delayed';
        }
        if ($this->estimated_arrival_on->toDateString() <= self::today()->addDays(config('shipping.near_arrival_days'))->toDateString()) {
            return 'near_arrival';
        }

        return 'in_transit';
    }

    public function scopeWithStatus(Builder $query, string $status): void
    {
        $today = self::today()->toDateString();
        $near = self::today()->addDays(config('shipping.near_arrival_days'))->toDateString();
        if ($status === 'delivered') {
            $query->whereNotNull('delivered_at');

            return;
        }
        $query->whereNull('delivered_at');
        if ($status === 'scheduled') {
            $query->whereDate('departed_on', '>', $today);
        } else {
            $query->whereDate('departed_on', '<=', $today);
            match ($status) {
                'delayed' => $query->whereDate('estimated_arrival_on', '<', $today),
                'near_arrival' => $query->whereDate('estimated_arrival_on', '>=', $today)->whereDate('estimated_arrival_on', '<=', $near),
                default => $query->whereDate('estimated_arrival_on', '>', $near),
            };
        }
    }

    /** @return array{status: string, label: string, progress: int} */
    public function trackingState(): array
    {
        $status = $this->status();
        $elapsed = (int) CarbonImmutable::parse($this->departed_on->toDateString(), config('shipping.timezone'))->diffInDays(self::today(), false);
        $progress = $status === 'delivered' ? 100 : max(0, min(95, (int) round($elapsed / max(1, $this->duration_days) * 100)));

        return ['status' => $status, 'label' => self::STATUSES[$status], 'progress' => $progress];
    }

    public function shippingService(): BelongsTo
    {
        return $this->belongsTo(ShippingService::class);
    }

    public function distributorInvoices(): HasMany
    {
        return $this->hasMany(DistributorInvoice::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
