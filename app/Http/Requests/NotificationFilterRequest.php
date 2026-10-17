<?php

namespace App\Http\Requests;

use App\Services\NotificationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NotificationFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['search' => ['nullable', 'string', 'max:100'], 'type' => ['nullable', Rule::in(array_keys(NotificationService::TYPES))], 'page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', Rule::in([5, 6, 10, 12, 15, 25, 50])]];
    }
}
