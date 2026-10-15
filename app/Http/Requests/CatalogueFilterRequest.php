<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CatalogueFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = ['search' => ['nullable', 'string', 'max:200'], 'view' => ['nullable', Rule::in(['list', 'cards'])], 'per_page' => ['nullable', Rule::in([5, 6, 10, 12, 15, 25, 50])], 'kind' => ['nullable', Rule::in(['daily', 'non_daily'])], 'low_stock' => ['nullable', 'boolean'], 'page' => ['nullable', 'integer', 'min:1'], 'has_products' => ['nullable', 'boolean'], 'create' => ['nullable', 'boolean'], 'edit' => ['nullable', 'integer', 'min:1']];
        foreach (['brand_ids' => 'brands', 'type_ids' => 'product_types', 'color_ids' => 'colors', 'distributor_ids' => 'distributors'] as $field => $table) {
            $rules[$field] = ['nullable', 'array', 'max:100'];
            $rules[$field.'.*'] = ['integer', 'distinct', Rule::exists($table, 'id')->whereNull('deleted_at')];
        }

        return $rules;
    }
}
