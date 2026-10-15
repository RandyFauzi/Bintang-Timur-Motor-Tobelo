<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')->whereNull('deleted_at')->where('is_active', true)],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000000'],
            'paid_amount' => ['required', 'numeric', 'min:0', 'max:9999999999999.99', 'decimal:0,2'],
            'payment_method' => ['required', Rule::in(['cash'])],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'items.required' => 'Tambahkan produk ke keranjang terlebih dahulu.',
            'items.*.product_id.distinct' => 'Produk yang sama tidak boleh dikirim dalam beberapa baris.',
            'items.*.product_id.exists' => 'Sebagian produk sudah tidak tersedia. Periksa kembali keranjang.',
            'items.*.quantity.min' => 'Jumlah produk minimal 1 unit.',
            'paid_amount.required' => 'Isi jumlah uang yang diterima.',
        ];
    }
}
