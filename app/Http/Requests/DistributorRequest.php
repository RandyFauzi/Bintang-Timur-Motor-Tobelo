<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DistributorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255'], 'company_name' => ['nullable', 'string', 'max:255'], 'phone' => ['nullable', 'string', 'max:50'], 'email' => ['nullable', 'email', 'max:255'], 'address' => ['nullable', 'string', 'max:2000'], 'city' => ['nullable', 'string', 'max:255'], 'contact_person' => ['nullable', 'string', 'max:255'], 'notes' => ['nullable', 'string', 'max:2000']];
    }
}
