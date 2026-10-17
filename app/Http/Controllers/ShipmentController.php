<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShipmentRequest;
use App\Models\Shipment;
use App\Models\ShippingService;
use App\Services\ShipmentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ShipmentController extends Controller
{
    public function index(ShipmentRequest $request, ShipmentService $tracking): View
    {
        $query = Shipment::query();
        if ($search = $request->validated('search')) {
            $query->where(function (Builder $query) use ($search): void {
                foreach (['reference', 'cargo_name', 'service_name', 'vessel_name', 'origin_port', 'destination_port'] as $field) {
                    $query->orWhere($field, 'like', '%'.$search.'%');
                }
            });
        }
        if ($status = $request->validated('status')) {
            $query->withStatus($status);
        }
        $shipments = $query->orderByRaw('delivered_at IS NOT NULL')->orderBy('estimated_arrival_on')->orderBy('id')->paginate(6)->withQueryString();
        $selected = $request->validated('shipment') ? Shipment::with('receiver')->findOrFail($request->integer('shipment')) : $shipments->first();
        $editId = $request->validated('edit') ?? old('_shipment_id');
        $editing = filter_var($editId, FILTER_VALIDATE_INT) ? Shipment::findOrFail($editId) : null;
        $serviceId = $request->validated('service_edit') ?? old('_service_id');
        $editingService = filter_var($serviceId, FILTER_VALIDATE_INT) ? ShippingService::findOrFail($serviceId) : null;

        return view('shipments.index', [
            'shipments' => $shipments, 'selected' => $selected, 'editing' => $editing,
            'services' => ShippingService::orderBy('name')->get(), 'editingService' => $editingService, 'counts' => $tracking->counts(),
        ]);
    }

    public function store(ShipmentRequest $request, ShipmentService $tracking): RedirectResponse
    {
        $shipment = $tracking->save($request->validated(), $request->user());

        return redirect()->route('shipments.index', ['shipment' => $shipment->id])->with('success', 'Pengiriman cargo berhasil ditambahkan.');
    }

    public function update(ShipmentRequest $request, Shipment $shipment, ShipmentService $tracking): RedirectResponse
    {
        $tracking->save($request->validated(), $request->user(), $shipment);

        return redirect()->route('shipments.index', ['shipment' => $shipment->id])->with('success', 'Informasi pengiriman diperbarui.');
    }

    public function arrive(ShipmentRequest $request, Shipment $shipment, ShipmentService $tracking): RedirectResponse
    {
        $tracking->arrive($shipment, $request->user(), $request->integer('version'));

        return redirect()->route('shipments.index', ['shipment' => $shipment->id])->with('success', 'Cargo dikonfirmasi sudah sampai.');
    }

    public function notifications(ShipmentRequest $request, ShipmentService $tracking): JsonResponse
    {
        $states = Shipment::whereKey($request->validated('ids', []))->get()->mapWithKeys(fn (Shipment $shipment): array => [$shipment->id => $shipment->trackingState()]);

        return response()->json($tracking->notifications() + ['states' => $states, 'counts' => $tracking->counts()])->header('Cache-Control', 'private, no-store');
    }
}
