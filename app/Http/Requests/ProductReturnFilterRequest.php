<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class ProductReturnFilterRequest extends CatalogueFilterRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_replace(parent::rules(), [
            'product_id' => ['nullable', 'integer', 'min:1', Rule::exists('products', 'id')->whereNull('deleted_at')->where('is_active', true)],
            'note_status' => ['nullable', Rule::in(['pending', 'credited'])],
            'status' => ['nullable', Rule::in(['active', 'cancelled', 'all'])],
            'edit' => ['nullable', 'uuid'],
            'mode' => ['nullable', Rule::in(['edit', 'cancel', 'delete', 'status', 'activity'])],
        ]);
    }
}
