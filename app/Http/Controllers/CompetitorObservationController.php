<?php

namespace App\Http\Controllers;

use App\Http\Requests\ObservationRequest;
use App\Models\CompetitorObservation;
use App\Services\CompetitorReportService;
use Illuminate\Http\RedirectResponse;

class CompetitorObservationController extends Controller
{
    public function store(ObservationRequest $request, CompetitorReportService $service): RedirectResponse
    {
        $service->save($request->validated(), $request->user()->id);

        return redirect()->route('reports.index')->with('success', 'Pengerjaan baru berhasil ditambahkan.');
    }

    public function update(ObservationRequest $request, CompetitorObservation $observation, CompetitorReportService $service): RedirectResponse
    {
        $service->save($request->validated(), $request->user()->id, $observation);

        return redirect()->route('reports.index')->with('success', 'Pengamatan berhasil diperbarui.');
    }
}
