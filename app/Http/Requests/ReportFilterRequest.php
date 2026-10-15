<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReportFilterRequest extends DashboardRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = array_merge(parent::rules(), [
            'search' => ['nullable', 'string', 'max:200'], 'view' => ['nullable', Rule::in(['list', 'cards'])],
            'per_page' => ['nullable', Rule::in([5, 6, 10, 12, 15, 25, 50])], 'page' => ['nullable', 'integer', 'min:1'],
            'workshop_id' => ['nullable', 'integer', 'exists:workshops,id'], 'hour' => ['nullable', 'integer', 'between:0,23'],
            'work_type_ids' => ['nullable', 'array', 'max:100'], 'work_type_ids.*' => ['integer', 'distinct', 'exists:work_types,id'],
            'edit' => ['nullable', 'integer', 'exists:competitor_observations,id'], 'create' => ['nullable', 'boolean'],
            'type_hour' => ['nullable', 'integer', 'between:0,23'],
            'type_workshop_id' => ['nullable', 'integer', 'exists:workshops,id'],
        ]);
        foreach (['type_', 'workshop_'] as $prefix) {
            $rules[$prefix.'start_date'] = ['bail', 'required_with:'.$prefix.'end_date', 'date_format:Y-m-d', 'before_or_equal:today'];
            $rules[$prefix.'end_date'] = ['bail', 'required_with:'.$prefix.'start_date', 'date_format:Y-m-d', 'after_or_equal:'.$prefix.'start_date', 'before_or_equal:today'];
        }

        return $rules;
    }

    /** @return list<\Closure> */
    public function after(): array
    {
        return array_merge(parent::after(), [function (Validator $validator): void {
            foreach (['type_', 'workshop_'] as $prefix) {
                $start = $prefix.'start_date';
                $end = $prefix.'end_date';
                if ($validator->errors()->hasAny([$start, $end]) || ! $this->filled([$start, $end])) {
                    continue;
                }
                if (CarbonImmutable::parse($this->input($start))->diffInDays(CarbonImmutable::parse($this->input($end))) > 365) {
                    $validator->errors()->add($end, 'Rentang tanggal maksimal 366 hari.');
                }
            }
        }]);
    }
}
