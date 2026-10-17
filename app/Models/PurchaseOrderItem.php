<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    protected $fillable = ['product_id', 'product_name', 'sku', 'unit', 'quantity', 'unit_price', 'actual_quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'actual_quantity' => 'integer', 'unit_price' => 'decimal:2'];
    }

    public function discrepancy(): string
    {
        if ($this->actual_quantity === null) {
            return 'Belum diperiksa';
        }
        if ($this->actual_quantity === 0) {
            return 'Tidak datang / Kosong';
        }
        $difference = $this->actual_quantity - $this->quantity;

        return $difference === 0 ? 'Sesuai' : ($difference < 0 ? 'Kurang '.abs($difference) : 'Lebih '.$difference);
    }
}
