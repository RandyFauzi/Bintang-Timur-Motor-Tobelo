<?php

namespace App\Services;

use App\Models\DistributorInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchasePlan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class PurchaseOrderDeletionService
{
    public static function selectionSnapshot(PurchaseOrder|PurchasePlan $entry): string
    {
        if ($entry instanceof PurchasePlan) {
            return hash('sha256', json_encode(['draft', $entry->id, $entry->version], JSON_THROW_ON_ERROR));
        }
        $plan = $entry->purchasePlan;
        $orders = $plan ? $plan->orders : collect([$entry]);

        return hash('sha256', json_encode([$plan?->id, $plan?->version, $orders->sortBy('id')->map(fn (PurchaseOrder $order): array => [$order->id, $order->version, $order->invoice?->id, $order->invoice?->version])->values()->all()], JSON_THROW_ON_ERROR));
    }

    /**
     * @param  list<array{type:string,id:int,version:int,snapshot:string}>  $selected
     * @return array{deleted:int,archived:int}
     */
    public function deleteMany(array $selected): array
    {
        return DB::transaction(function () use ($selected): array {
            $entries = collect($selected);
            $orderIds = $entries->where('type', 'order')->pluck('id');
            $planIds = PurchaseOrder::whereKey($orderIds)->pluck('purchase_plan_id')->filter()
                ->merge($entries->where('type', 'draft')->pluck('id'))->unique();
            $plans = PurchasePlan::whereKey($planIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $orders = PurchaseOrder::where(fn (Builder $query): Builder => $query->whereIn('id', $orderIds)->orWhereIn('purchase_plan_id', $planIds))
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $invoices = DistributorInvoice::whereIn('purchase_order_id', $orders->keys())->orderBy('id')->lockForUpdate()->get()->keyBy('purchase_order_id');
            foreach ($orders as $order) {
                $order->setRelation('purchasePlan', $plans->get($order->purchase_plan_id));
                $order->setRelation('invoice', $invoices->get($order->id));
            }
            foreach ($plans as $plan) {
                $plan->setRelation('orders', $orders->where('purchase_plan_id', $plan->id)->values());
            }
            $targets = [];
            $seen = [];
            foreach ($selected as $row) {
                $entry = $row['type'] === 'draft' ? $plans->get($row['id']) : $orders->get($row['id']);
                if (! $entry || $entry->version !== (int) $row['version'] || self::selectionSnapshot($entry) !== $row['snapshot']) {
                    throw ValidationException::withMessages(['selected' => 'Pengadaan atau tagihan terkait sudah berubah atau tidak tersedia. Muat ulang halaman dan periksa pilihan Anda.']);
                }
                if ($entry instanceof PurchasePlan && ($entry->status !== 'draft' || $entry->orders->isNotEmpty())) {
                    throw ValidationException::withMessages(['selected' => 'Draft sudah difinalisasi. Muat ulang halaman.']);
                }
                if ($entry instanceof PurchaseOrder && $entry->archived_at !== null) {
                    throw ValidationException::withMessages(['selected' => 'Pengadaan sudah diarsipkan. Muat ulang halaman.']);
                }
                $key = $entry instanceof PurchasePlan ? 'plan:'.$entry->id : ($entry->purchase_plan_id ? 'plan:'.$entry->purchase_plan_id : 'order:'.$entry->id);
                if (isset($seen[$key])) {
                    throw ValidationException::withMessages(['selected' => 'Pengadaan yang sama tidak boleh dipilih dua kali.']);
                }
                $seen[$key] = true;
                $targets[] = $entry;
            }
            $result = ['deleted' => 0, 'archived' => 0];
            foreach ($targets as $entry) {
                if ($entry instanceof PurchasePlan) {
                    $entry->delete();
                    $result['deleted']++;
                } else {
                    $archived = $this->delete($entry, $entry->version);
                    $result[$archived ? 'archived' : 'deleted']++;
                }
            }

            return $result;
        });
    }

    public function delete(PurchaseOrder $purchaseOrder, int $version): bool
    {
        return DB::transaction(function () use ($purchaseOrder, $version): bool {
            [$plan, $orders] = $this->lockOrders($purchaseOrder, $version);
            $invoices = [];
            $processed = false;
            foreach ($orders as $order) {
                $invoice = $order->invoice()->lockForUpdate()->first();
                $invoices[$order->id] = $invoice;
                $processed = $processed || $order->note_issued || $order->shipment_finalized_at !== null
                    || $order->items()->whereNotNull('actual_quantity')->exists()
                    || ($invoice && ($invoice->payments()->exists() || $invoice->paid_cents > 0 || $invoice->shipment_id !== null || $invoice->claim_amount_cents > 0
                        || $invoice->version > 1 || $invoice->invoiced_on !== null || trim($invoice->supplier_reference ?? '') !== ''
                        || trim($invoice->discrepancy_notes ?? '') !== '' || trim($invoice->notes ?? '') !== ''));
            }
            if ($processed) {
                $this->assertArchiveReady();
                foreach ($orders as $order) {
                    if ($order->archived_at === null) {
                        $order->archived_at = now();
                        $order->version++;
                        $order->save();
                    }
                }

                return true;
            }
            foreach ($orders as $order) {
                $invoices[$order->id]?->delete();
                $order->delete();
            }
            $plan?->delete();

            return false;
        });
    }

    public function restore(PurchaseOrder $purchaseOrder, int $version): void
    {
        $this->assertArchiveReady();
        DB::transaction(function () use ($purchaseOrder, $version): void {
            [, $orders] = $this->lockOrders($purchaseOrder, $version);
            foreach ($orders as $order) {
                if ($order->archived_at !== null) {
                    $order->archived_at = null;
                    $order->version++;
                    $order->save();
                }
            }
        });
    }

    private function assertArchiveReady(): void
    {
        if (! Schema::hasColumn('purchase_orders', 'archived_at')) {
            throw ValidationException::withMessages(['purchase_order' => 'Fitur arsip belum siap. Administrator perlu menyelesaikan pembaruan database. Data pengadaan tetap tersimpan.']);
        }
    }

    /** @return array{?PurchasePlan, Collection<int, PurchaseOrder>} */
    private function lockOrders(PurchaseOrder $purchaseOrder, int $version): array
    {
        $plan = $purchaseOrder->purchase_plan_id ? PurchasePlan::lockForUpdate()->findOrFail($purchaseOrder->purchase_plan_id) : null;
        $orders = $plan ? $plan->orders()->orderBy('id')->lockForUpdate()->get() : new Collection([PurchaseOrder::lockForUpdate()->findOrFail($purchaseOrder->id)]);
        $order = $orders->firstWhere('id', $purchaseOrder->id);
        abort_unless($order, 404);
        if ($order->version !== $version) {
            throw ValidationException::withMessages(['version' => 'Pengadaan telah berubah. Muat ulang halaman sebelum melanjutkan.']);
        }

        return [$plan, $orders];
    }
}
