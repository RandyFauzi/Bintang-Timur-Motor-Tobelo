<?php

namespace App\View\Components;

use App\Services\DashboardService;
use Illuminate\Support\Collection;
use Illuminate\View\Component;
use Illuminate\View\View;

class AppLayout extends Component
{
    /** @param array{count: int, products: Collection}|null $stockOverview */
    public function __construct(private DashboardService $dashboardService, public ?array $stockOverview = null) {}

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        $this->stockOverview ??= $this->dashboardService->stockOverview();

        return view('layouts.app');
    }
}
