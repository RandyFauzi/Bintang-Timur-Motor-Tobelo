<?php

namespace App\Http\Requests;

use App\Models\ProductImportColumn;
use Illuminate\Foundation\Http\FormRequest;

class ProductImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['file' => ['required', 'file', 'mimes:xlsx', 'extensions:xlsx', 'max:5120'], 'mapping' => ['sometimes', 'array:'.implode(',', array_keys(ProductImportColumn::DEFAULTS))], 'mapping.*' => ['nullable', 'string', 'max:100']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['file.required' => 'Pilih file Excel terlebih dahulu.', 'file.mimes' => 'Gunakan file Excel .xlsx sesuai template.', 'file.extensions' => 'Gunakan file Excel .xlsx.', 'file.max' => 'Ukuran file maksimal 5 MB.'];
    }
}
