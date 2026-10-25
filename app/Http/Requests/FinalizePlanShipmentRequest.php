<?php

namespace App\Http\Requests;

use App\Models\PurchasePlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FinalizePlanShipmentRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $orders = $this->input('orders');
        if (is_array($orders)) {
            foreach ($orders as $key => $order) {
                if (is_array($order) && (! isset($order['package_count']) || $order['package_count'] === '' || $order['package_count'] === null)) {
                    $orders[$key]['package_count'] = 1;
                }
            }
            $this->merge(['orders' => $orders]);
        }
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $plan = $this->route('purchasePlan');
        abort_unless($plan instanceof PurchasePlan, 404);

        return [
            'shipping_service_id' => ['required', 'integer', Rule::exists('shipping_services', 'id')->where('is_active', true)],
            'origin_port' => ['required', 'string', 'max:255'],
            'departed_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'orders' => ['required', 'array', 'min:1'],
            'orders.*.id' => ['required', 'integer', 'distinct', Rule::exists('purchase_orders', 'id')->where('purchase_plan_id', $plan->id)],
            'orders.*.version' => ['required', 'integer', 'min:1'],
            'orders.*.package_count' => ['required', 'integer', 'min:1', 'max:1000000'],
            'orders.*.shipping_cost' => ['nullable', 'numeric', 'min:0', 'max:90000000000000', 'regex:/^\d+(\.\d{1,2})?$/'],
        ];
    }
}
