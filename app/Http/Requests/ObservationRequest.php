<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ObservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'workshop_id' => ['required', 'integer', 'exists:workshops,id'],
            'observed_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'observed_hour' => ['required', 'integer', 'between:0,23'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*' => ['array:work_type_id,quantity'],
            'items.*.work_type_id' => ['required', 'integer', 'distinct', 'exists:work_types,id'],
            'items.*.quantity' => ['required', 'integer', 'between:1,100000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['items.required' => 'Pilih minimal satu jenis pengerjaan.', 'items.min' => 'Pilih minimal satu jenis pengerjaan.', 'items.*.quantity.between' => 'Jumlah pengerjaan harus antara 1 dan 100.000.', 'items.*.work_type_id.distinct' => 'Jenis pengerjaan tidak boleh berulang.', 'observed_on.before_or_equal' => 'Tanggal pengamatan tidak boleh melewati hari ini.'];
    }
}
