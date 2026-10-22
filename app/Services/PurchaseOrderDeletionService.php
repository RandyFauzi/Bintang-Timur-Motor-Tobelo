<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\PurchasePlan;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class PurchaseOrderDeletionService
{
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
