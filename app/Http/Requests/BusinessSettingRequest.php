<?php

namespace App\Http\Requests;

use App\Services\ImageStorageService;
use Illuminate\Foundation\Http\FormRequest;

class BusinessSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-business') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['business_name' => ['required', 'string', 'max:100'], 'logo' => ImageStorageService::rules(), 'remove_logo' => ['sometimes', 'boolean']];
    }
}
