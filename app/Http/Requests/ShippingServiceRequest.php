<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShippingServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('shipping_services')->ignore($this->route('shippingService'))],
            'duration' => ['required', 'integer', 'min:1', 'max:'.($this->input('duration_unit') === 'weeks' ? 520 : 3640)],
            'duration_unit' => ['required', Rule::in(['days', 'weeks'])],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
