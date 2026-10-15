<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-users') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:200'],
            'role' => ['nullable', Rule::in(['admin', 'staff'])],
            'view' => ['nullable', Rule::in(['list', 'cards'])],
            'per_page' => ['nullable', Rule::in([5, 6, 10, 12, 15, 25, 50])],
            'page' => ['nullable', 'integer', 'min:1'],
            'create' => ['nullable', 'boolean'],
            'edit' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
