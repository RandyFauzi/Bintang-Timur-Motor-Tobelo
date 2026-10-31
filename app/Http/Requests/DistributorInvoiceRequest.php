<?php

namespace App\Http\Requests;

use App\Models\DistributorInvoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DistributorInvoiceRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->isMethod('GET') && $this->filled('status') && ! is_array($this->input('status'))) {
            $this->merge(['status' => [$this->input('status')]]);
        }
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        if ($this->isMethod('PATCH')) {
            $invoice = $this->route('distributorInvoice');
            $minDate = $invoice?->purchaseOrder?->ordered_on?->toDateString() ?? $invoice?->invoiced_on?->toDateString();

            return [
                'version' => ['required', 'integer', 'min:1'],
                'due_on' => array_values(array_filter(['required', 'date_format:Y-m-d', $minDate ? 'after_or_equal:'.$minDate : null])),
                'invoiced_on' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:due_on'],
                'total' => ['nullable', 'numeric', 'min:0.01', 'max:90000000000000'],
                'shipment_id' => ['nullable', 'integer', Rule::exists('shipments', 'id')],
                'supplier_reference' => ['nullable', 'string', 'max:255'],
                'notes' => ['nullable', 'string', 'max:2000'],
                'discrepancy_notes' => ['nullable', 'string', 'max:4000'],
                'claim_amount' => ['nullable', 'numeric', 'min:0', 'max:90000000000000', 'regex:/^\d+(\.\d{1,2})?$/'],
                'received_at' => ['prohibited'], 'received_on' => ['prohibited'],
            ];
        }

        return ['search' => ['nullable', 'string', 'max:255'], 'distributor_id' => ['nullable', 'integer'], 'status' => ['nullable', 'array', 'max:4'], 'status.*' => ['required', 'string', 'distinct', Rule::in(array_keys(DistributorInvoice::STATUSES))], 'timing' => ['nullable', Rule::in(['1', '2', '3', '4', 'near', 'overdue'])]];
    }
}
