<?php

namespace App\Http\Requests;

use App\Models\DistributorInvoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DistributorInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        if ($this->isMethod('PATCH')) {
            return ['version' => ['required', 'integer', 'min:1'], 'due_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$this->route('distributorInvoice')->purchaseOrder->ordered_on->toDateString()], 'supplier_reference' => ['nullable', 'string', 'max:255'], 'notes' => ['nullable', 'string', 'max:2000']];
        }

        return ['search' => ['nullable', 'string', 'max:255'], 'distributor_id' => ['nullable', 'integer'], 'status' => ['nullable', Rule::in(array_keys(DistributorInvoice::STATUSES))], 'timing' => ['nullable', Rule::in(['near', 'overdue'])]];
    }
}
