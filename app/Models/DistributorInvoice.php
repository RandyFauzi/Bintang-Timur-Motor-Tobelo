<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DistributorInvoice extends Model
{
    use HasFactory;

    public const STATUSES = ['unpaid' => 'Belum Dibayar', 'partial' => 'Dibayar Sebagian', 'paid' => 'Lunas'];

    public const REMINDER_DAYS = 7;

    protected $fillable = ['purchase_order_id', 'reference', 'due_on', 'total_cents', 'supplier_reference', 'notes'];

    protected function casts(): array
    {
        return ['due_on' => 'immutable_date', 'total_cents' => 'integer', 'paid_cents' => 'integer', 'version' => 'integer'];
    }

    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now(config('shipping.timezone'))->startOfDay();
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(DistributorPayment::class);
    }

    public function remainingCents(): int
    {
        return max(0, $this->total_cents - $this->paid_cents);
    }

    public function status(): string
    {
        return $this->remainingCents() === 0 ? 'paid' : ($this->paid_cents > 0 ? 'partial' : 'unpaid');
    }

    public function timingLabel(): string
    {
        if ($this->remainingCents() === 0) {
            return 'Lunas';
        }
        $days = (int) self::today()->diffInDays(CarbonImmutable::parse($this->due_on->toDateString(), config('shipping.timezone')), false);
        if ($days < 0) {
            return 'Terlambat '.abs($days).' hari';
        }
        if ($days === 0) {
            return 'Jatuh tempo hari ini';
        }

        return $days <= self::REMINDER_DAYS ? 'Jatuh tempo '.$days.' hari lagi' : 'Belum jatuh tempo';
    }

    public function scopeOutstanding(Builder $query): void
    {
        $query->whereColumn('paid_cents', '<', 'total_cents');
    }

    public function scopeNeedsReminder(Builder $query): void
    {
        $query->outstanding()->whereDate('due_on', '<=', self::today()->addDays(self::REMINDER_DAYS)->toDateString());
    }
}
