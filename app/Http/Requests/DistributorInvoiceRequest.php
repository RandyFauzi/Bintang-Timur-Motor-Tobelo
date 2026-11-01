<?php

namespace App\Http\Requests;

use App\Models\Distributor;
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

        if ($this->isMethod('PATCH') && $this->filled('distributor_name') && ! $this->filled('distributor_id')) {
            $name = trim((string) $this->input('distributor_name'));
            $match = Distributor::whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($name)])->value('id');
            if ($match) {
                $this->merge(['distributor_id' => $match]);
            }
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
            $minDate = $invoice?->purchaseOrder?->ordered_on?->toDateString();

            return [
                'version' => ['required', 'integer', 'min:1'],
                'distributor_id' => ['nullable', 'integer', Rule::exists('distributors', 'id')],
                'distributor_name' => ['nullable', 'string', 'max:255'],
                'reference' => ['nullable', 'string', 'max:255', Rule::unique('distributor_invoices', 'reference')->ignore($invoice?->id)],
                'due_on' => array_values(array_filter(['required', 'date_format:Y-m-d', $minDate ? 'after_or_equal:'.$minDate : null])),
                'invoiced_on' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:due_on'],
                'total' => ['sometimes', 'required', 'numeric', 'min:0.01', 'max:90000000000000', 'regex:/^\d+(\.\d{1,2})?$/'],
                'shipment_id' => ['nullable', 'integer', Rule::exists('shipments', 'id')],
                'supplier_reference' => ['nullable', 'string', 'max:255'],
                'notes' => ['nullable', 'string', 'max:2000'],
                'discrepancy_notes' => ['nullable', 'string', 'max:4000'],
                'claim_amount' => ['nullable', 'numeric', 'min:0', 'max:90000000000000', 'regex:/^\d+(\.\d{1,2})?$/'],
                'received_at' => ['prohibited'],
                'received_on' => ['nullable', 'date_format:Y-m-d', Rule::prohibitedIf(! $invoice?->isManual() || ($this->exists('shipment_id') ? $this->filled('shipment_id') : $invoice?->shipment_id !== null))],
            ];
        }

        return ['search' => ['nullable', 'string', 'max:255'], 'distributor_id' => ['nullable', 'integer'], 'status' => ['nullable', 'array', 'max:4'], 'status.*' => ['required', 'string', 'distinct', Rule::in(array_keys(DistributorInvoice::STATUSES))], 'timing' => ['nullable', Rule::in(['1', '2', '3', '4', 'near', 'overdue'])]];
    }

    protected function getRedirectUrl(): string
    {
        if ($this->isMethod('PATCH') && $this->input('_dialog') === 'invoice-edit') {
            return route('distributor-invoices.show', $this->route('distributorInvoice'));
        }

        return parent::getRedirectUrl();
    }

    public function attributes(): array
    {
        return [
            'distributor_id' => 'distributor',
            'distributor_name' => 'nama distributor',
            'reference' => 'nomor invoice',
            'due_on' => 'jatuh tempo',
            'invoiced_on' => 'tanggal faktur',
            'received_on' => 'tanggal masuk barang',
            'total' => 'total tagihan',
            'shipment_id' => 'kargo kapal',
            'supplier_reference' => 'nomor nota distributor',
            'notes' => 'catatan tagihan',
            'discrepancy_notes' => 'keterangan retur/selisih',
            'claim_amount' => 'nilai klaim',
        ];
    }
}
