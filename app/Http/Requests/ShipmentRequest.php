<?php

namespace App\Http\Requests;

use App\Models\Shipment;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        if ($this->isMethod('get')) {
            return [
                'search' => ['nullable', 'string', 'max:100'],
                'status' => ['nullable', Rule::in(array_keys(Shipment::STATUSES))],
                'shipment' => ['nullable', 'integer', 'min:1'],
                'edit' => ['nullable', 'integer', 'min:1'],
                'service_edit' => ['nullable', 'integer', 'min:1'],
                'ids' => ['sometimes', 'array', 'max:50'], 'ids.*' => ['integer', 'distinct', 'min:1'],
            ];
        }
        $version = ['version' => ['required', 'integer', 'min:1']];
        if ($this->routeIs('shipments.arrive')) {
            return $version;
        }

        return [
            'reference' => ['nullable', 'string', 'max:100', Rule::unique('shipments')->ignore($this->route('shipment'))],
            'shipping_service_id' => ['required', 'integer', Rule::exists('shipping_services', 'id')->where(fn (Builder $query) => $query->where('is_active', true)->orWhere('id', $this->route('shipment')?->shipping_service_id ?? 0))],
            'cargo_name' => ['required', 'string', 'max:255'],
            'vessel_name' => ['nullable', 'string', 'max:255'],
            'origin_port' => ['required', 'string', 'max:255'],
            'destination_port' => ['required', 'string', 'max:255'],
            'destination_address' => ['nullable', 'string', 'max:1000'],
            'package_count' => ['required', 'integer', 'min:1', 'max:1000000'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0', 'max:90000000000000', 'regex:/^\d+(\.\d{1,2})?$/'],
            'departed_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ] + ($this->isMethod('patch') ? $version : []);
    }
}
