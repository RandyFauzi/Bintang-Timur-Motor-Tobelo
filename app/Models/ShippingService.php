<?php

namespace App\Models;

use Database\Factories\ShippingServiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingService extends Model
{
    /** @use HasFactory<ShippingServiceFactory> */
    use HasFactory;

    protected $fillable = ['name', 'duration_days', 'is_active'];

    protected function casts(): array
    {
        return ['duration_days' => 'integer', 'is_active' => 'boolean'];
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }
}
