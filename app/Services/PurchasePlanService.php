<?php

namespace App\Services;

use App\Models\Distributor;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchasePlan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PurchasePlanService
{
    public function __construct(private SupplierProductService $supplierProducts) {}

    /** @param array<string, mixed> $data */
    public function save(array $data, ?PurchasePlan $existing = null): PurchasePlan
    {
        return DB::transaction(function () use ($data, $existing): PurchasePlan {
            $plan = $existing
                ? PurchasePlan::lockForUpdate()->findOrFail($existing->id)
                : PurchasePlan::where('request_id', $data['request_id'])->lockForUpdate()->first();
            if ($plan) {
                if ($plan->request_id !== $data['request_id']) {
                    throw ValidationException::withMessages(['request_id' => 'Identitas rencana tidak sesuai. Muat ulang halaman.']);
                }
                if ($plan->status === 'finalized') {
                    if ($data['intent'] === 'finalize' && $plan->name === $data['name'] && $plan->planned_on->toDateString() === $data['planned_on'] && $plan->items == $data['items']) {
                        return $plan;
                    }
                    throw ValidationException::withMessages(['plan' => 'Rencana yang sudah difinalisasi tidak dapat diubah.']);
                }
                if ($plan->version !== (int) $data['version']) {
                    if ($data['intent'] === 'draft' && $plan->version === (int) $data['version'] + 1
                        && $plan->name === $data['name'] && $plan->planned_on->toDateString() === $data['planned_on'] && $plan->items == $data['items']) {
                        return $plan;
                    }
                    throw ValidationException::withMessages(['version' => 'Rencana telah berubah. Muat ulang halaman sebelum menyimpan.']);
                }
            } elseif ((int) $data['version'] !== 0) {
                throw ValidationException::withMessages(['version' => 'Versi rencana tidak sesuai.']);
            }
            $plan ??= new PurchasePlan(['request_id' => $data['request_id'], 'reference' => 'PGD-'.str_replace('-', '', $data['planned_on']).'-'.Str::upper(substr(str_replace('-', '', $data['request_id']), 0, 12)), 'version' => 0]);
            $plan->fill(['name' => $data['name'], 'planned_on' => $data['planned_on'], 'items' => $data['items'], 'status' => 'draft']);
            $plan->version++;
            $plan->save();
            if ($data['intent'] !== 'finalize') {
                return $plan;
            }
            $this->supplierProducts->ensureSchemaReady();
            $groups = [];
            foreach ($data['items'] as $item) {
                foreach ($item['allocations'] as $allocation) {
                    $groups[$allocation['distributor_id']][] = [$item, $allocation['quantity']];
                }
            }
            ksort($groups);
            foreach ($groups as $distributorId => $rows) {
                $distributor = Distributor::lockForUpdate()->find($distributorId);
                if (! $distributor || ! $distributor->is_active) {
                    throw ValidationException::withMessages(['items' => 'Distributor tidak lagi aktif. Periksa kembali alokasi.']);
                }
                $order = PurchaseOrder::create([
                    'purchase_plan_id' => $plan->id, 'request_id' => (string) Str::uuid(),
                    'reference' => $plan->reference.'-D'.$distributorId, 'distributor_id' => $distributorId,
                    'distributor_name' => $distributor->name, 'ordered_on' => $plan->planned_on,
                    'notes' => $plan->name,
                ]);
                foreach ($rows as [$item, $quantity]) {
                    $selectedProduct = ! empty($item['product_id']) ? Product::find($item['product_id']) : null;
                    if (! empty($item['product_id']) && (! $selectedProduct || ! $selectedProduct->is_active)) {
                        throw ValidationException::withMessages(['items' => 'Produk katalog tidak lagi tersedia. Periksa kembali rencana.']);
                    }
                    $supplierProduct = $selectedProduct ? $this->supplierProducts->forDistributor($selectedProduct, $distributor, (float) $item['unit_price']) : null;
                    $order->items()->create([
                        'product_id' => $supplierProduct?->id,
                        'product_name' => $item['product_name'],
                        'sku' => $supplierProduct?->sku ?? ($item['sku'] ?? ''), 'unit' => $item['unit'], 'quantity' => $quantity,
                        'unit_price' => $supplierProduct && $supplierProduct->id !== $selectedProduct->id ? $supplierProduct->purchase_price : $item['unit_price'],
                    ]);
                }
            }
            $plan->update(['status' => 'finalized', 'finalized_at' => now()]);

            return $plan;
        });
    }
}
