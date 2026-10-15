<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['sku', 'barcode', 'brand_id', 'type_id', 'color_id', 'name', 'description', 'purchase_price', 'selling_price', 'stock_minimum', 'stock_maximum', 'unit', 'image', 'is_active', 'product_kind'];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(ProductType::class, 'type_id');
    }

    public function color(): BelongsTo
    {
        return $this->belongsTo(Color::class);
    }

    public function stock(): HasOne
    {
        return $this->hasOne(ProductStock::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function distributors(): BelongsToMany
    {
        return $this->belongsToMany(Distributor::class, 'product_distributors')
            ->withPivot('distributor_sku', 'purchase_price', 'is_primary')
            ->withTimestamps();
    }
}
