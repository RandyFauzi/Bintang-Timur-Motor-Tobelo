<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleItem extends Model
{
    use HasFactory, HasUuids;
    protected $fillable = ['sale_id', 'product_id', 'product_name', 'sku', 'barcode', 'quantity', 'unit_price', 'discount', 'subtotal'];

    public function sale() { return $this->belongsTo(Sale::class); }
    public function product() { return $this->belongsTo(Product::class); } // Optional if product deleted
}