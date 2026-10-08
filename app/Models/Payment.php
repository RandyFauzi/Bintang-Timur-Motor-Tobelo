<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;
    protected $fillable = ['sale_id', 'method', 'amount', 'reference_number', 'paid_at'];
    protected $casts = [
        'paid_at' => 'datetime',
    ];

    public function sale() { return $this->belongsTo(Sale::class); }
}