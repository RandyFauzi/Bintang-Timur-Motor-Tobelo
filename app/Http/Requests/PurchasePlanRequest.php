<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PurchasePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'request_id' => ['required', 'uuid'],
            'version' => ['required', 'integer', 'min:0'],
            'intent' => ['required', 'in:draft,finalize'],
            'autosave' => ['sometimes', 'boolean'],
            'name' => ['required', 'string', 'max:255'],
            'planned_on' => ['required', 'date_format:Y-m-d'],
            'items' => ['present', 'array', 'list', 'max:100'],
            'items.*' => ['array:product_id,product_name,color,product_kind,brand,part_type,current_stock,quantity,sku,unit,unit_price,allocations'],
            'items.*.product_id' => ['nullable', 'integer'],
            'items.*.product_name' => ['nullable', 'string', 'max:255'],
            'items.*.color' => ['nullable', 'string', 'max:100'],
            'items.*.product_kind' => ['nullable', Rule::in(['daily', 'non_daily', 'variation'])],
            'items.*.brand' => ['nullable', 'string', 'max:255'],
            'items.*.part_type' => ['nullable', 'string', 'max:255'],
            'items.*.current_stock' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'items.*.quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'items.*.sku' => ['nullable', 'string', 'max:255'],
            'items.*.unit' => ['required', 'string', 'max:50'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:999999999', 'decimal:0,2'],
            'items.*.allocations' => ['present', 'array', 'list', 'max:100'],
            'items.*.allocations.*' => ['array:distributor_id,quantity'],
            'items.*.allocations.*.distributor_id' => ['nullable', 'integer', Rule::exists('distributors', 'id')->whereNull('deleted_at')->where('is_active', true)],
            'items.*.allocations.*.quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || $this->input('intent') !== 'finalize') {
                return;
            }
            $items = $this->input('items');
            if ($items === []) {
                $validator->errors()->add('items', 'Tambahkan minimal satu produk sebelum finalisasi.');
            }
            foreach ($items as $index => $item) {
                if (trim($item['product_name'] ?? '') === '' || (int) $item['quantity'] < 1) {
                    $validator->errors()->add('items.'.$index.'.quantity', 'Nama produk dan kebutuhan positif wajib diisi.');
                }
                $allocated = 0;
                $suppliers = [];
                foreach ($item['allocations'] as $allocation) {
                    $id = $allocation['distributor_id'] ?? null;
                    if (! $id || (int) $allocation['quantity'] < 1 || in_array((int) $id, $suppliers, true)) {
                        $validator->errors()->add('items.'.$index.'.allocations', 'Pilih distributor berbeda dengan jumlah alokasi positif.');
                    }
                    $suppliers[] = (int) $id;
                    $allocated += (int) $allocation['quantity'];
                }
                if ($allocated !== (int) $item['quantity']) {
                    $validator->errors()->add('items.'.$index.'.allocations', 'Total alokasi harus tepat sama dengan kebutuhan produk.');
                }
            }
        }];
    }
}
