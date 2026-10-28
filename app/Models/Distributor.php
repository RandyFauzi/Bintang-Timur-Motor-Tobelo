<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Distributor extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'company_name', 'phone', 'email', 'address', 'city', 'province', 'contact_person', 'notes', 'is_active'];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_distributors')
            ->withPivot('distributor_sku', 'purchase_price', 'is_primary')
            ->withTimestamps();
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
