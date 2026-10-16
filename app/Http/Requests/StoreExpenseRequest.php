<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['category' => ['required', 'string', 'max:100'], 'description' => ['nullable', 'string', 'max:2000'], 'amount' => ['required', 'numeric', 'min:1', 'max:9999999999999.99', 'decimal:0,2'], 'expense_date' => ['required', 'date_format:Y-m-d']];
    }
}
