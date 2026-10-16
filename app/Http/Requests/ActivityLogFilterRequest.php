<?php

namespace App\Http\Requests;

use App\Models\ActivityLog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActivityLogFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view-activity-logs') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['search' => ['nullable', 'string', 'max:200'], 'user_id' => ['nullable', 'integer', 'min:1'], 'module' => ['nullable', Rule::in(array_keys(ActivityLog::MODULES))], 'action' => ['nullable', Rule::in(array_keys(ActivityLog::ACTIONS))], 'result' => ['nullable', Rule::in(array_keys(ActivityLog::RESULTS))], 'start_date' => ['nullable', 'date_format:Y-m-d'], 'end_date' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('start_date'), 'after_or_equal:start_date')], 'detail' => ['nullable', 'integer', 'min:1'], 'page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', Rule::in([5, 6, 10, 12, 15, 25, 50])]];
    }
}
