<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class DashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        $rules = [];

        foreach (['', 'revenue_', 'activity_'] as $prefix) {
            $rules[$prefix.'start_date'] = ['bail', 'required_with:'.$prefix.'end_date', 'date_format:Y-m-d', 'before_or_equal:today'];
            $rules[$prefix.'end_date'] = ['bail', 'required_with:'.$prefix.'start_date', 'date_format:Y-m-d', 'after_or_equal:'.$prefix.'start_date', 'before_or_equal:today'];
        }

        return $rules;
    }

    /** @return list<\Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (['', 'revenue_', 'activity_'] as $prefix) {
                $start = $prefix.'start_date';
                $end = $prefix.'end_date';

                if ($validator->errors()->hasAny([$start, $end]) || ! $this->filled([$start, $end])) {
                    continue;
                }

                if (CarbonImmutable::parse($this->input($start))->diffInDays(CarbonImmutable::parse($this->input($end))) > 365) {
                    $validator->errors()->add($end, 'Rentang tanggal maksimal 366 hari.');
                }
            }
        }];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            '*.required_with' => 'Tanggal awal dan akhir harus diisi bersama.',
            '*.date_format' => 'Gunakan tanggal dengan format YYYY-MM-DD.',
            '*.after_or_equal' => 'Tanggal akhir harus sama atau setelah tanggal awal.',
            '*.before_or_equal' => 'Tanggal tidak boleh melewati hari ini.',
        ];
    }
}
