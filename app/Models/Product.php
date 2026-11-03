<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    public const KINDS = [
        'daily' => 'Harian',
        'non_daily' => 'Non-Harian',
        'variation' => 'Variasi',
        'body' => 'Body',
        'printilan' => 'Printilan',
    ];

    public static function kindLabel(?string $kind): string
    {
        if (! $kind) {
            return '–';
        }
        if (isset(self::KINDS[$kind])) {
            return self::KINDS[$kind];
        }

        return Str::headline(str_replace(['_', '-'], ' ', $kind));
    }

    public static function normalizeKind(?string $kind): string
    {
        if (! $kind) {
            return 'daily';
        }
        $trimmed = trim($kind);
        $lower = mb_strtolower($trimmed);

        return match ($lower) {
            'daily', 'harian', 'produk harian' => 'daily',
            'non_daily', 'non-daily', 'non-harian', 'non harian', 'produk non harian', 'produk non-harian' => 'non_daily',
            'variation', 'variasi', 'produk variasi' => 'variation',
            'body', 'produk body' => 'body',
            'printilan', 'produk printilan' => 'printilan',
            default => Str::limit(Str::slug($trimmed, '_'), 20, ''),
        };
    }

    /** @return array<string, string> */
    public static function allKinds(): array
    {
        $kinds = self::KINDS;
        try {
            $existing = self::query()
                ->distinct()
                ->whereNotNull('product_kind')
                ->whereNotIn('product_kind', ['', 'daily', 'non_daily', 'variation', 'body', 'printilan'])
                ->orderBy('product_kind')
                ->pluck('product_kind');

            foreach ($existing as $custom) {
                $kinds[$custom] = self::kindLabel($custom);
            }
        } catch (\Throwable) {
            // Fallback during migrations/unit testing without table
        }

        return $kinds;
    }

    /** @return array<string, string> */
    public static function tabKinds(): array
    {
        $tabs = ['' => 'Semua Produk'];
        foreach (self::allKinds() as $key => $label) {
            $tabs[$key] = 'Produk '.$label;
        }

        return $tabs;
    }

    /** @return array<string> */
    public static function allKindLabels(): array
    {
        return array_values(self::allKinds());
    }

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
