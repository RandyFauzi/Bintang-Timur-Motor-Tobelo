<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDistributorInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'distributor_id' => ['required', 'integer', Rule::exists('distributors', 'id')],
            'reference' => ['nullable', 'string', 'max:255', Rule::unique('distributor_invoices', 'reference')],
            'invoiced_on' => ['nullable', 'date_format:Y-m-d'],
            'due_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:invoiced_on'],
            'total' => ['required', 'numeric', 'min:0.01', 'max:90000000000000'],
            'supplier_reference' => ['nullable', 'string', 'max:255'],
            'shipment_id' => ['nullable', 'integer', Rule::exists('shipments', 'id')],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'distributor_id.required' => 'Pilih distributor terlebih dahulu.',
            'distributor_id.exists' => 'Distributor yang dipilih tidak valid.',
            'reference.unique' => 'Nomor invoice ini sudah digunakan.',
            'due_on.required' => 'Tanggal jatuh tempo wajib diisi.',
            'due_on.after_or_equal' => 'Jatuh tempo tidak boleh sebelum tanggal faktur.',
            'total.required' => 'Total tagihan wajib diisi.',
            'total.min' => 'Total tagihan harus lebih dari 0.',
        ];
    }
}
