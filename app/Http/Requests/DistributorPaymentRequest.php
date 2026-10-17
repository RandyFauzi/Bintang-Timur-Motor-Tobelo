<?php

namespace App\Http\Requests;

use App\Models\DistributorInvoice;
use App\Models\DistributorPayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DistributorPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'request_id' => ['required', 'uuid'], 'version' => ['required', 'integer', 'min:1'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999999', 'regex:/^\d+(\.\d{1,2})?$/'],
            'paid_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.DistributorInvoice::today()->toDateString()],
            'method' => ['required', Rule::in(array_keys(DistributorPayment::METHODS))],
            'reference' => ['nullable', 'string', 'max:255'], 'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
