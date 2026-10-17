<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProductBulkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = [
            'selected' => ['required', 'array', 'min:1', 'max:100'],
            'selected.*' => ['required', 'integer', 'distinct', 'min:1', Rule::exists('products', 'id')->whereNull('deleted_at')->where('is_active', true)],
        ];
        if ($this->routeIs('products.bulk.update')) {
            $rules += [
                'changes' => ['required', 'array:purchase_price,selling_price,quantity,stock_minimum,product_kind,brand_id,type_id,color_id'],
                'changes.purchase_price' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
                'changes.selling_price' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
                'changes.quantity' => ['nullable', 'integer', 'min:0', 'max:100000000'],
                'changes.stock_minimum' => ['nullable', 'integer', 'min:0', 'max:100000000'],
                'changes.product_kind' => ['nullable', Rule::in(array_keys(Product::KINDS))],
                'changes.brand_id' => ['nullable', 'integer', Rule::exists('brands', 'id')->whereNull('deleted_at')->where('is_active', true)],
                'changes.type_id' => ['nullable', 'integer', Rule::exists('product_types', 'id')->whereNull('deleted_at')->where('is_active', true)],
                'changes.color_id' => ['nullable', 'integer', Rule::exists('colors', 'id')->whereNull('deleted_at')->where('is_active', true)],
            ];
        }

        return $rules;
    }

    /** @return list<\Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $changes = $this->input('changes', []);
            if ($this->routeIs('products.bulk.update') && is_array($changes) && array_filter($changes, fn (mixed $value): bool => $value !== null && $value !== '') === []) {
                $validator->errors()->add('changes', 'Isi minimal satu kolom untuk edit massal.');
            }
        }];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['selected.required' => 'Pilih produk terlebih dahulu.', 'selected.*.exists' => 'Sebagian produk tidak tersedia. Muat ulang halaman.', 'selected.*.distinct' => 'Produk yang sama tidak boleh dipilih dua kali.'];
    }
}
