<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use HasFactory, HasUuids;
    protected $fillable = ['invoice_number', 'user_id', 'subtotal', 'discount', 'tax', 'grand_total', 'payment_status', 'status', 'customer_name', 'customer_phone', 'paid_amount', 'change_amount', 'sold_at'];
    protected $casts = [
        'sold_at' => 'datetime',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function items() { return $this->hasMany(SaleItem::class); }
    public function payments() { return $this->hasMany(Payment::class); }
}