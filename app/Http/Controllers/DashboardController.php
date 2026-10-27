<?php

namespace App\Http\Controllers;

use App\Http\Requests\DashboardRequest;
use App\Services\DashboardService;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(DashboardRequest $request, DashboardService $dashboardService): View
    {
        $canViewSales = Gate::allows('view-sales-metrics');

        return view('dashboard', array_merge(
            $dashboardService->getDashboard($request->validated(), $canViewSales),
            ['canViewSales' => $canViewSales]
        ));
    }
}
