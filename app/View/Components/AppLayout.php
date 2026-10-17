<?php

namespace App\View\Components;

use App\Services\DashboardService;
use App\Services\DistributorInvoiceService;
use App\Services\ShipmentService;
use Illuminate\Support\Collection;
use Illuminate\View\Component;
use Illuminate\View\View;

class AppLayout extends Component
{
    public array $shipmentNotifications = [];

    public array $invoiceNotifications = [];

    /** @param array{count: int, products: Collection}|null $stockOverview */
    public function __construct(private DashboardService $dashboardService, private ShipmentService $shipmentService, private DistributorInvoiceService $invoiceService, public ?array $stockOverview = null) {}

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        $this->stockOverview ??= $this->dashboardService->stockOverview();

        $this->shipmentNotifications = $this->shipmentService->notifications();
        $this->invoiceNotifications = $this->invoiceService->notifications();

        return view('layouts.app');
    }
}
