<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkDeleteDistributorInvoiceRequest extends FormRequest
{
    protected $redirectRoute = 'distributor-invoices.index';

    public function authorize(): bool
    {
        return $this->user()?->can('manage-invoices') ?? false;
    }

    public function rules(): array
    {
        return [
            'selected' => ['required', 'array', 'min:1', 'max:100'],
            'selected.*' => ['required', 'array:id,version'],
            'selected.*.id' => ['required', 'integer', 'min:1', 'distinct'],
            'selected.*.version' => ['required', 'integer', 'min:1'],
        ];
    }
}
