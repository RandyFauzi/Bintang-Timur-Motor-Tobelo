<?php

namespace App\Models;

use Database\Factories\ProductImportColumnFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductImportColumn extends Model
{
    /** @use HasFactory<ProductImportColumnFactory> */
    use HasFactory;

    protected $fillable = ['field', 'label', 'aliases'];

    public const DEFAULTS = [
        'name' => ['label' => 'Nama Produk', 'aliases' => ['name', 'nama', 'nama barang', 'product name']],
        'brand_name' => ['label' => 'Merk', 'aliases' => ['merek', 'brand', 'brand name']],
        'type_name' => ['label' => 'Jenis Part', 'aliases' => ['jenis', 'part', 'kategori', 'category']],
        'color_name' => ['label' => 'Warna', 'aliases' => ['color', 'colour']],
        'purchase_price' => ['label' => 'Modal', 'aliases' => ['harga beli', 'harga modal', 'purchase price', 'cost']],
        'selling_price' => ['label' => 'Harga Jual', 'aliases' => ['harga', 'selling price', 'price']],
        'product_kind' => ['label' => 'Tipe Produk', 'aliases' => ['product kind', 'tipe']],
        'supplier_names' => ['label' => 'Distributor', 'aliases' => ['supplier', 'suppliers']],
        'barcode' => ['label' => 'Barcode', 'aliases' => ['kode barcode', 'ean']],
        'sku' => ['label' => 'SKU', 'aliases' => ['kode produk', 'kode barang']],
        'quantity' => ['label' => 'Jumlah Stok', 'aliases' => ['stok', 'stock', 'qty', 'quantity', 'total stok']],
        'stock_minimum' => ['label' => 'Stok Minimum', 'aliases' => ['minimum stock', 'min stock', 'stok minimal']],
        'id' => ['label' => 'ID', 'aliases' => ['id produk', 'product id', 'product_id']],
    ];

    protected function casts(): array
    {
        return ['aliases' => 'array'];
    }

    /** @return array<string, array{label: string, aliases: list<string>}> */
    public static function definitions(): array
    {
        $definitions = self::DEFAULTS;
        foreach (self::all() as $column) {
            if (isset($definitions[$column->field])) {
                $definitions[$column->field] = ['label' => $column->label, 'aliases' => $column->aliases ?? []];
            }
        }

        return $definitions;
    }
}
