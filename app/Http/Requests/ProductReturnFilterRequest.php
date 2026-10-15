<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class ProductReturnFilterRequest extends CatalogueFilterRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_replace(parent::rules(), [
            'note_status' => ['nullable', Rule::in(['pending', 'credited'])],
            'status' => ['nullable', Rule::in(['active', 'cancelled', 'all'])],
            'edit' => ['nullable', 'uuid'],
            'mode' => ['nullable', Rule::in(['edit', 'cancel', 'delete', 'status', 'activity'])],
        ]);
    }
}
