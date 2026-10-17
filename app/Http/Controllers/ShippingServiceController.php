<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShippingServiceRequest;
use App\Models\ShippingService;
use Illuminate\Http\RedirectResponse;

class ShippingServiceController extends Controller
{
    public function store(ShippingServiceRequest $request): RedirectResponse
    {
        $data = $request->validated();
        ShippingService::create(['name' => $data['name'], 'duration_days' => $data['duration'] * ($data['duration_unit'] === 'weeks' ? 7 : 1), 'is_active' => $data['is_active']]);

        return redirect()->route('shipments.index', ['services' => 1])->with('success', 'Jasa pengiriman ditambahkan.');
    }

    public function update(ShippingServiceRequest $request, ShippingService $shippingService): RedirectResponse
    {
        $data = $request->validated();
        $shippingService->update(['name' => $data['name'], 'duration_days' => $data['duration'] * ($data['duration_unit'] === 'weeks' ? 7 : 1), 'is_active' => $data['is_active']]);

        return redirect()->route('shipments.index', ['services' => 1])->with('success', 'Jasa pengiriman diperbarui. Estimasi pengiriman lama tetap dipertahankan.');
    }
}
