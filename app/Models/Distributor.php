<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Distributor extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = ['name', 'company_name', 'phone', 'email', 'address', 'city', 'province', 'contact_person', 'notes', 'is_active'];

    public function products() {
        return $this->belongsToMany(Product::class, 'product_distributors')
            ->withPivot('distributor_sku', 'purchase_price', 'is_primary')
            ->withTimestamps();
    }
}