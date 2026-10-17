<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'request_id' => ['required', 'uuid'],
            'distributor_id' => ['required', 'integer', Rule::exists('distributors', 'id')->whereNull('deleted_at')->where('is_active', true)],
            'ordered_on' => ['required', 'date_format:Y-m-d'], 'expected_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:ordered_on'],
            'payment_term_months' => ['nullable', 'integer', Rule::in([2, 3])],
            'due_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:ordered_on'],
            'notes' => ['nullable', 'string', 'max:2000'], 'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*' => ['required', 'array:product_id,quantity,unit_price'],
            'items.*.product_id' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')->whereNull('deleted_at')->where('is_active', true)],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:999999999', 'decimal:0,2'],
        ];
    }
}
