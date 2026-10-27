<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->boolean('_sync_distributors') && ! $this->has('distributor_ids')) {
            if ($this->filled('distributor_id')) {
                $this->merge(['distributor_ids' => [$this->integer('distributor_id')]]);
            } elseif (! $this->filled('distributor_name')) {
                $this->merge(['distributor_ids' => []]);
            }
        }
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $presence = $this->isMethod('post') ? 'required' : 'sometimes';
        $product = $this->route('product');

        return [
            'name' => [$presence, 'required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($product)],
            'barcode' => ['nullable', 'string', 'max:100', Rule::unique('products', 'barcode')->ignore($product)],
            'purchase_price' => [$presence, 'required', 'numeric', 'min:0', 'max:999999999999'],
            'selling_price' => [$presence, 'required', 'numeric', 'min:0', 'max:999999999999'],
            'stock_minimum' => ['required', 'integer', 'min:0', 'max:100000000'],
            'quantity' => ['required_without:initial_stock', 'integer', 'min:0', 'max:100000000'],
            'initial_stock' => ['sometimes', 'integer', 'min:0', 'max:100000000'],
            'product_kind' => ['sometimes', Rule::in(array_keys(Product::KINDS))],
            'brand_id' => ['nullable', Rule::exists('brands', 'id')->whereNull('deleted_at')],
            'type_id' => ['nullable', Rule::exists('product_types', 'id')->whereNull('deleted_at')],
            'color_id' => ['nullable', Rule::exists('colors', 'id')->whereNull('deleted_at')],
            'brand_name' => ['nullable', 'string', 'max:255'], 'type_name' => ['nullable', 'string', 'max:255'], 'color_name' => ['nullable', 'string', 'max:255'],
            'distributor_name' => ['nullable', 'string', 'max:255'],
            'distributor_id' => ['nullable', 'integer', Rule::exists('distributors', 'id')->whereNull('deleted_at')->where('is_active', true)],
            'distributor_ids' => ['sometimes', 'array', 'max:1'],
            'distributor_ids.*' => ['integer', 'distinct', Rule::exists('distributors', 'id')->whereNull('deleted_at')->where('is_active', true)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['*.required' => 'Kolom ini wajib diisi.', '*.numeric' => 'Masukkan angka yang valid.', '*.integer' => 'Masukkan bilangan bulat.', '*.min' => 'Nilai tidak boleh negatif.', '*.exists' => 'Pilihan tidak tersedia.', '*.unique' => 'Kode ini sudah digunakan.', 'quantity.required_without' => 'Jumlah stok wajib diisi.', 'distributor_ids.max' => 'Satu ID produk hanya boleh memiliki satu distributor.'];
    }
}
