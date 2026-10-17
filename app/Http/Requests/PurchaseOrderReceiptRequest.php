<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseOrderReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:1'], 'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*' => ['required', 'array:id,actual_quantity'],
            'items.*.id' => ['required', 'integer', 'distinct', Rule::exists('purchase_order_items', 'id')->where('purchase_order_id', $this->route('purchaseOrder')->id)],
            'items.*.actual_quantity' => ['present', 'nullable', 'integer', 'min:0', 'max:1000000'],
        ];
    }
}
