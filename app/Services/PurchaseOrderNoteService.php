<?php

namespace App\Services;

use App\Models\Distributor;
use App\Models\DistributorInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchasePlan;
use App\Models\Shipment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PurchaseOrderNoteService
{
    /** @param array<string, mixed> $data */
    public function save(PurchaseOrder $purchaseOrder, array $data): void
    {
        DB::transaction(function () use ($purchaseOrder, $data): void {
            $order = PurchaseOrder::lockForUpdate()->findOrFail($purchaseOrder->id);
            $this->assertPlan($order);
            $this->assertEditable($order, (int) $data['version']);
            $invoice = $order->invoice()->lockForUpdate()->first();
            if ($invoice && ($invoice->paid_cents > 0 || $invoice->payments()->exists() || $invoice->shipment_id !== null || $invoice->claim_amount_cents > 0)) {
                throw ValidationException::withMessages(['note' => 'Nota sudah terkait pembayaran, pengiriman, atau klaim dan tidak dapat diubah.']);
            }
            $issued = (bool) $data['note_issued'];
            foreach ($data['items'] as $row) {
                $item = $order->items()->findOrFail($row['id']);
                $item->invoice_quantity = $issued ? (int) $row['invoice_quantity'] : null;
                $item->save();
            }
            $order->note_issued = $issued;
            $order->supplier_note_number = $issued ? $data['supplier_note_number'] : null;
            $order->supplier_note_on = $issued ? $data['supplier_note_on'] : null;
            $order->supplier_note_total_cents = $issued ? DistributorInvoiceService::cents($data['supplier_note_total']) : null;
            $order->version++;
            $order->save();
            if (! $issued) {
                $invoice?->delete();

                return;
            }
            $total = $this->invoiceTotal($order);
            $invoice ??= new DistributorInvoice(['purchase_order_id' => $order->id, 'reference' => 'INV-'.$order->reference, 'due_on' => CarbonImmutable::parse($order->supplier_note_on)->addMonthsNoOverflow(3)->toDateString()]);
            if ($invoice->due_on->toDateString() < $order->supplier_note_on->toDateString()) {
                $invoice->due_on = $order->supplier_note_on->toDateString();
            }
            $invoice->supplier_reference = $order->supplier_note_number;
            $invoice->invoiced_on = $order->supplier_note_on;
            $invoice->total_cents = $total;
            if ($invoice->exists) {
                $invoice->version++;
            }
            $invoice->save();
        });
    }

    /** @param array<string, mixed> $data */
    public function finalize(PurchaseOrder $purchaseOrder, array $data, User $user, ShipmentService $tracking): void
    {
        $plan = PurchasePlan::findOrFail($purchaseOrder->purchase_plan_id);
        $this->finalizeShipment($plan, [
            'shipping_service_id' => $data['shipping_service_id'],
            'origin_port' => $data['origin_port'],
            'departed_on' => $data['departed_on'],
            'notes' => $data['notes'] ?? null,
            'orders' => [
                [
                    'id' => $purchaseOrder->id,
                    'version' => $data['version'],
                    'package_count' => $data['package_count'] ?? 1,
                    'shipping_cost' => $data['shipping_cost'] ?? null,
                ],
            ],
        ], $user, $tracking);
    }

    /** @param array<string, mixed> $data */
    public function finalizeShipment(PurchasePlan $plan, array $data, User $user, ShipmentService $tracking): Shipment
    {
        return DB::transaction(function () use ($plan, $data, $user, $tracking): Shipment {
            $this->assertPlanStatus($plan);
            $orderInputs = collect($data['orders'])->keyBy('id');
            $orderIds = $orderInputs->keys()->all();

            $orders = PurchaseOrder::whereIn('id', $orderIds)
                ->where('purchase_plan_id', $plan->id)
                ->lockForUpdate()
                ->get();

            if ($orders->count() !== count($orderIds)) {
                throw ValidationException::withMessages(['orders' => 'Pesanan tidak ditemukan pada rencana pengadaan ini.']);
            }

            $allFinalized = $orders->every(fn (PurchaseOrder $order): bool => $order->shipment_finalized_at !== null);
            if ($allFinalized) {
                $shipmentIds = $orders->map(fn (PurchaseOrder $order) => $order->invoice?->shipment_id)->unique()->filter();
                if ($shipmentIds->count() === 1) {
                    $shipment = Shipment::find($shipmentIds->first());
                    if ($shipment && $shipment->shipping_service_id === (int) $data['shipping_service_id'] && $shipment->origin_port === $data['origin_port'] && $shipment->departed_on->toDateString() === $data['departed_on']) {
                        return $shipment;
                    }
                }
                throw ValidationException::withMessages(['note' => 'Pengiriman sudah difinalisasi. Muat ulang halaman.']);
            }

            if ($orders->contains(fn (PurchaseOrder $order): bool => $order->shipment_finalized_at !== null)) {
                throw ValidationException::withMessages(['note' => 'Sebagian pesanan sudah difinalisasi. Muat ulang halaman.']);
            }

            $totalPackageCount = 0;
            $totalShippingCostCents = 0;

            foreach ($orders as $order) {
                $itemData = $orderInputs->get($order->id);
                $this->assertEditable($order, (int) $itemData['version']);

                if (! $order->note_issued || ! $order->supplier_note_number || ! $order->supplier_note_on) {
                    throw ValidationException::withMessages(['note' => 'Terbitkan dan simpan nota sebelum finalisasi pengiriman.']);
                }

                $invoice = $order->invoice()->lockForUpdate()->first();
                if (! $invoice) {
                    throw ValidationException::withMessages(['note' => 'Terbitkan dan simpan nota sebelum finalisasi pengiriman.']);
                }

                $order->load('items');
                if ($order->items->isEmpty() || $order->items->contains(fn (PurchaseOrderItem $item): bool => $item->invoice_quantity === null) || $order->items->sum('invoice_quantity') < 1) {
                    throw ValidationException::withMessages(['items' => 'Pengiriman memerlukan minimal satu unit barang pada nota.']);
                }

                if ($data['departed_on'] < $order->supplier_note_on->toDateString()) {
                    throw ValidationException::withMessages(['departed_on' => 'Tanggal berangkat tidak boleh sebelum tanggal nota.']);
                }

                if ($invoice->shipment_id !== null || $invoice->total_cents !== $this->invoiceTotal($order)) {
                    throw ValidationException::withMessages(['note' => 'Data tagihan sudah berubah. Periksa nota sebelum finalisasi.']);
                }

                $packageCount = (int) ($itemData['package_count'] ?? 1);
                $shippingCostCents = ! empty($itemData['shipping_cost']) ? DistributorInvoiceService::cents($itemData['shipping_cost']) : 0;

                $totalPackageCount += $packageCount;
                $totalShippingCostCents += $shippingCostCents;
            }

            $cargoName = $orders->count() === 1
                ? 'Pengadaan '.$orders->first()->distributor_name
                : 'Pengadaan '.$orders->pluck('distributor_name')->unique()->join(', ');

            $defaultNotes = $orders->map(fn (PurchaseOrder $o) => 'PO '.$o->reference.' | Nota '.$o->supplier_note_number)->join("\n");
            $notes = ! empty($data['notes']) ? trim($data['notes']) : $defaultNotes;

            $shipment = $tracking->save([
                'shipping_service_id' => $data['shipping_service_id'],
                'cargo_name' => Str::limit($cargoName, 255, ''),
                'origin_port' => $data['origin_port'],
                'destination_port' => config('shipping.destination_port'),
                'destination_address' => config('shipping.destination_address'),
                'package_count' => $totalPackageCount,
                'shipping_cost_cents' => $totalShippingCostCents,
                'departed_on' => $data['departed_on'],
                'notes' => $notes,
            ], $user);

            foreach ($orders as $order) {
                $itemData = $orderInputs->get($order->id);
                $packageCount = (int) ($itemData['package_count'] ?? 1);
                $shippingCostCents = ! empty($itemData['shipping_cost']) ? DistributorInvoiceService::cents($itemData['shipping_cost']) : 0;

                $invoice = $order->invoice;
                $invoice->shipment_id = $shipment->id;
                $invoice->version++;
                $invoice->save();

                $order->package_count = $packageCount;
                $order->shipping_cost_cents = $shippingCostCents;
                $order->shipment_finalized_at = now();
                $order->version++;
                $order->save();
            }

            return $shipment;
        });
    }

    private function assertPlan(PurchaseOrder $order): void
    {
        if (! $order->purchase_plan_id || PurchasePlan::findOrFail($order->purchase_plan_id)->status !== 'finalized') {
            throw ValidationException::withMessages(['note' => 'Finalisasi rencana pengadaan terlebih dahulu.']);
        }
    }

    private function assertPlanStatus(PurchasePlan $plan): void
    {
        if ($plan->status !== 'finalized') {
            throw ValidationException::withMessages(['note' => 'Finalisasi rencana pengadaan terlebih dahulu.']);
        }
    }

    private function assertEditable(PurchaseOrder $order, int $version): void
    {
        if ($order->shipment_finalized_at || $order->version !== $version) {
            throw ValidationException::withMessages(['version' => 'Nota sudah berubah atau pengiriman sudah difinalisasi. Muat ulang halaman.']);
        }
    }

    private function invoiceTotal(PurchaseOrder $order): int
    {
        if ($order->supplier_note_total_cents !== null) {
            return $order->supplier_note_total_cents;
        }
        $total = 0;
        foreach ($order->items()->get() as $item) {
            $subtotal = DistributorInvoiceService::cents($item->unit_price) * ($item->invoice_quantity ?? 0);
            if ($subtotal > 9000000000000000 - $total) {
                throw ValidationException::withMessages(['items' => 'Total nota terlalu besar. Pisahkan pengadaan.']);
            }
            $total += $subtotal;
        }

        return $total;
    }

    /** @return list<array<string, mixed>> */
    public function snapshots(PurchasePlan $plan): array
    {
        $sources = [];
        foreach ($plan->items as $index => $item) {
            foreach ($item['allocations'] ?? [] as $allocation) {
                $sources[$allocation['distributor_id']][] = $index;
            }
        }
        $cities = Distributor::withTrashed()->whereIn('id', $plan->orders->pluck('distributor_id'))->pluck('city', 'id');

        return $plan->orders->map(function (PurchaseOrder $order) use ($plan, $sources, $cities): array {
            $invoice = $order->invoice;
            $shipment = $invoice?->shipment;
            $totalCents = $order->supplier_note_total_cents ?? $invoice?->total_cents;
            $shippingCostCents = $order->shipping_cost_cents ?: ($shipment?->shipping_cost_cents ?? 0);
            $packageCount = $order->package_count ?: ($shipment?->package_count ?? 1);

            return [
                'id' => $order->id, 'reference' => $order->reference, 'distributor_id' => $order->distributor_id,
                'distributor_name' => $order->distributor_name, 'origin_port' => $cities[$order->distributor_id] ?? '',
                'version' => $order->version, 'note_issued' => $order->note_issued,
                'supplier_note_number' => $order->supplier_note_number ?? '', 'supplier_note_on' => $order->supplier_note_on?->toDateString() ?? '',
                'supplier_note_total' => $order->note_issued && $totalCents !== null ? intdiv($totalCents, 100).'.'.str_pad((string) ($totalCents % 100), 2, '0', STR_PAD_LEFT) : '',
                'package_count' => $packageCount,
                'shipping_cost' => $shippingCostCents > 0 ? intdiv($shippingCostCents, 100).'.'.str_pad((string) ($shippingCostCents % 100), 2, '0', STR_PAD_LEFT) : '',
                'finalized' => $order->shipment_finalized_at !== null,
                'can_edit' => $order->shipment_finalized_at === null && (! $invoice || ($invoice->paid_cents === 0 && $invoice->shipment_id === null && $invoice->claim_amount_cents === 0 && $invoice->payments->isEmpty())),
                'shipment_url' => $shipment ? route('shipments.index', ['shipment' => $shipment->id]) : null,
                'shipment_status' => $shipment?->status(), 'invoice_url' => $invoice ? route('distributor-invoices.show', $invoice) : null,
                'save_url' => route('purchase-plans.orders.note', [$plan, $order]), 'finalize_url' => route('purchase-plans.orders.finalize', [$plan, $order]),
                'items' => $order->items->sortBy('id')->values()->map(function (PurchaseOrderItem $item, int $position) use ($order, $plan, $sources): array {
                    $index = $sources[$order->distributor_id][$position] ?? null;
                    $source = $index !== null ? ($plan->items[$index] ?? null) : null;
                    if ($source && ($source['product_name'] ?? '') !== $item->product_name) {
                        $index = null;
                    }

                    return ['id' => $item->id, 'product_name' => $item->product_name, 'color' => $index !== null ? ($source['color'] ?? '') : '', 'unit' => $item->unit, 'source_index' => $index, 'quantity' => $item->quantity, 'invoice_quantity' => $item->invoice_quantity];
                })->all(),
            ];
        })->all();
    }

    /** @return array<string, mixed> */
    public function snapshot(PurchasePlan $plan, int $orderId): array
    {
        return collect($this->snapshots($plan))->firstWhere('id', $orderId) ?? [];
    }

    /** @return list<array<string, mixed>> */
    public function shortages(PurchasePlan $plan): array
    {
        $shortages = [];
        foreach ($this->snapshots($plan) as $order) {
            if (! $order['note_issued']) {
                continue;
            }
            foreach ($order['items'] as $item) {
                $quantity = $item['quantity'] - ($item['invoice_quantity'] ?? $item['quantity']);
                if ($item['source_index'] === null) {
                    continue;
                }
                $index = $item['source_index'];
                $shortages[$index] ??= ['source_index' => $index, 'product_name' => $item['product_name'], 'color' => $item['color'], 'quantity' => 0];
                $shortages[$index]['quantity'] += $quantity;
            }
        }

        return array_values(array_filter($shortages, fn (array $item): bool => $item['quantity'] > 0));
    }
}
