<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DistributorPayment extends Model
{
    use HasFactory;

    public const METHODS = ['transfer' => 'Transfer Bank', 'cash' => 'Tunai', 'other' => 'Lainnya'];

    protected $fillable = ['request_id', 'amount_cents', 'paid_on', 'method', 'reference', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['paid_on' => 'date', 'amount_cents' => 'integer'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(DistributorInvoice::class, 'distributor_invoice_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
