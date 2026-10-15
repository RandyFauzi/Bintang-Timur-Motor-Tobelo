<?php

namespace App\Http\Controllers;

use App\Http\Requests\DashboardRequest;
use App\Services\DashboardService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(DashboardRequest $request, DashboardService $dashboardService): View
    {
        return view('dashboard', $dashboardService->getDashboard($request->validated()));
    }
}
