<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $route = $this->route()->getName();
        $version = ['required', 'integer', 'min:1'];
        $quantity = ['required', 'integer', 'min:1', 'max:100000000'];
        $noteStatus = ['required', Rule::in(['pending', 'credited'])];

        return match ($route) {
            'returns.store' => [
                'request_id' => ['required', 'uuid'],
                'product_id' => ['required', 'integer', Rule::exists('products', 'id')->whereNull('deleted_at')->where('is_active', true)],
                'quantity' => $quantity,
                'note_status' => $noteStatus,
            ],
            'returns.bulk' => [
                'selected' => ['required', 'array', 'min:1', 'max:100'],
                'selected.*.id' => ['required', 'uuid', 'distinct'],
                'selected.*.version' => $version,
                'quantity' => ['nullable', 'required_without:note_status', 'integer', 'min:1', 'max:100000000'],
                'note_status' => ['nullable', 'required_without:quantity', Rule::in(['pending', 'credited'])],
            ],
            'returns.cancel' => ['version' => $version, 'quantity' => $quantity],
            'returns.destroy' => ['version' => $version],
            default => [
                'version' => $version,
                'quantity' => ['nullable', 'required_without:note_status', 'integer', 'min:1', 'max:100000000'],
                'note_status' => ['nullable', 'required_without:quantity', Rule::in(['pending', 'credited'])],
            ],
        };
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'quantity.min' => 'Jumlah retur minimal 1 unit.',
            'product_id.exists' => 'Pilih produk aktif yang masih tersedia.',
            'quantity.required_without' => 'Isi jumlah retur atau pilih status nota.',
            'note_status.required_without' => 'Pilih status nota atau isi jumlah retur.',
        ];
    }
}
