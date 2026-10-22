<?php

namespace App\Http\Requests;

use App\Models\PurchaseOrder;
use App\Models\PurchasePlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseOrderNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $plan = $this->route('purchasePlan');
        $order = $this->route('purchaseOrder');
        abort_unless($plan instanceof PurchasePlan && $order instanceof PurchaseOrder && $order->purchase_plan_id === $plan->id, 404);
        $version = ['version' => ['required', 'integer', 'min:1']];
        if ($this->routeIs('purchase-plans.orders.finalize')) {
            return $version + [
                'shipping_service_id' => ['required', 'integer', Rule::exists('shipping_services', 'id')->where('is_active', true)],
                'origin_port' => ['required', 'string', 'max:255'],
                'departed_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
                'package_count' => ['sometimes', 'integer', 'min:1', 'max:1000000'],
            ];
        }

        return $version + [
            'note_issued' => ['required', 'boolean'],
            'supplier_note_number' => ['nullable', 'required_if:note_issued,true', 'string', 'max:255'],
            'supplier_note_on' => ['nullable', 'required_if:note_issued,true', 'date_format:Y-m-d', 'after_or_equal:'.$order->ordered_on->toDateString()],
            'supplier_note_total' => ['nullable', 'required_if:note_issued,true', 'numeric', 'min:0', 'max:90000000000000', 'regex:/^\d+(\.\d{1,2})?$/'],
            'items' => ['required', 'array', 'size:'.$order->items()->count()],
            'items.*' => ['required', 'array:id,invoice_quantity'],
            'items.*.id' => ['required', 'integer', 'distinct', Rule::exists('purchase_order_items', 'id')->where('purchase_order_id', $order->id)],
            'items.*.invoice_quantity' => ['nullable', 'required_if:note_issued,true', 'integer', 'min:0', 'max:1000000'],
        ];
    }
}
