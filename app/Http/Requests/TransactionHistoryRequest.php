<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransactionHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:200'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', ...($this->filled('start_date') ? ['after_or_equal:start_date'] : [])],
            'status' => ['nullable', Rule::in(['completed', 'cancelled'])],
            'payment_status' => ['nullable', Rule::in(['paid', 'unpaid', 'partial'])],
            'payment_method' => ['nullable', 'string', 'max:255'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'view' => ['nullable', Rule::in(['list', 'cards'])],
            'per_page' => ['nullable', Rule::in([5, 6, 10, 12, 15, 25, 50])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['end_date.after_or_equal' => 'Tanggal akhir harus sama atau setelah tanggal mulai.'];
    }
}
