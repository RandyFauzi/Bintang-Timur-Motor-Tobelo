<?php

namespace App\Http\Controllers;

use App\Http\Requests\FinalizePlanShipmentRequest;
use App\Http\Requests\PurchaseOrderNoteRequest;
use App\Models\PurchaseOrder;
use App\Models\PurchasePlan;
use App\Services\PurchaseOrderNoteService;
use App\Services\ShipmentService;
use Illuminate\Http\JsonResponse;

class PurchaseOrderNoteController extends Controller
{
    public function update(PurchaseOrderNoteRequest $request, PurchasePlan $purchasePlan, PurchaseOrder $purchaseOrder, PurchaseOrderNoteService $notes): JsonResponse
    {
        $notes->save($purchaseOrder, $request->validated());

        return response()->json(['order' => $notes->snapshot($purchasePlan->load(['orders.items', 'orders.invoice.shipment', 'orders.invoice.payments']), $purchaseOrder->id), 'message' => 'Nota distributor berhasil disimpan.']);
    }

    public function finalize(PurchaseOrderNoteRequest $request, PurchasePlan $purchasePlan, PurchaseOrder $purchaseOrder, PurchaseOrderNoteService $notes, ShipmentService $tracking): JsonResponse
    {
        $notes->finalize($purchaseOrder, $request->validated(), $request->user(), $tracking);

        return response()->json(['order' => $notes->snapshot($purchasePlan->load(['orders.items', 'orders.invoice.shipment', 'orders.invoice.payments']), $purchaseOrder->id), 'message' => 'Pengiriman difinalisasi. Nota terkunci dan dapat dilacak melalui Pelacakan Pengiriman.']);
    }

    public function finalizeShipment(FinalizePlanShipmentRequest $request, PurchasePlan $purchasePlan, PurchaseOrderNoteService $notes, ShipmentService $tracking): JsonResponse
    {
        $shipment = $notes->finalizeShipment($purchasePlan, $request->validated(), $request->user(), $tracking);
        $orderCount = count($request->validated()['orders']);

        return response()->json([
            'shipment_id' => $shipment->id,
            'orders' => $notes->snapshots($purchasePlan->fresh(['orders.items', 'orders.invoice.shipment', 'orders.invoice.payments'])),
            'message' => $orderCount > 1
                ? 'Pengiriman bersama untuk '.$orderCount.' distributor berhasil difinalisasi.'
                : 'Pengiriman difinalisasi. Nota terkunci dan dapat dilacak melalui Pelacakan Pengiriman.',
        ]);
    }
}
