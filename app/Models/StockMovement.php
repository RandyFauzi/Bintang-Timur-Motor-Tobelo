<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    use HasFactory;
    protected $fillable = ['product_id', 'type', 'quantity', 'stock_before', 'stock_after', 'reference_type', 'reference_id', 'note', 'created_by'];

    public function product() { return $this->belongsTo(Product::class); }
    public function user() { return $this->belongsTo(User::class, 'created_by'); }
    public function reference() { return $this->morphTo(); }
}