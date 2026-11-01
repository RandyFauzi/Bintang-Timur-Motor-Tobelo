<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkDeletePurchaseOrderRequest extends FormRequest
{
    protected $redirectRoute = 'purchase-orders.index';

    public function authorize(): bool
    {
        return $this->user()?->can('manage-purchase-orders') ?? false;
    }

    public function rules(): array
    {
        return [
            'selected' => ['required', 'array', 'min:1', 'max:100'],
            'selected.*' => ['required', 'array:type,id,version,snapshot'],
            'selected.*.type' => ['required', 'in:draft,order'],
            'selected.*.id' => ['required', 'integer', 'min:1'],
            'selected.*.version' => ['required', 'integer', 'min:1'],
            'selected.*.snapshot' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
        ];
    }
}
