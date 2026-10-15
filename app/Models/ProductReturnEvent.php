<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductReturnEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'actor_name', 'action', 'stock_delta', 'before_state', 'after_state'];

    protected function casts(): array
    {
        return ['before_state' => 'array', 'after_state' => 'array', 'stock_delta' => 'integer'];
    }

    public function productReturn(): BelongsTo
    {
        return $this->belongsTo(ProductReturn::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
