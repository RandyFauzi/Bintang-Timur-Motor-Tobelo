<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DashboardService
{
    /**
     * @param  array<string, string>  $filters
     * @return array{range: array, revenueRange: array, activityRange: array, summary: array, previousSummary: array, changes: array, revenueSeries: array, hourlySeries: array, topProducts: Collection, stockOverview: array}
     */
    public function getDashboard(array $filters, bool $canViewSales = true): array
    {
        $start = CarbonImmutable::parse($filters['start_date'] ?? now()->subDays(6)->toDateString())->startOfDay();
        $end = CarbonImmutable::parse($filters['end_date'] ?? now()->toDateString())->endOfDay();
        $revenueStart = CarbonImmutable::parse($filters['revenue_start_date'] ?? $start)->startOfDay();
        $revenueEnd = CarbonImmutable::parse($filters['revenue_end_date'] ?? $end)->endOfDay();
        $activityStart = CarbonImmutable::parse($filters['activity_start_date'] ?? $start)->startOfDay();
        $activityEnd = CarbonImmutable::parse($filters['activity_end_date'] ?? $end)->endOfDay();
        $days = (int) $start->diffInDays($end->startOfDay()) + 1;
        $previousSummary = $canViewSales ? $this->salesSummary($start->subDays($days), $start->subDay()->endOfDay()) : ['revenue' => 0.0, 'transactions' => 0];
        $summary = $canViewSales ? $this->salesSummary($start, $end) : ['revenue' => 0.0, 'transactions' => 0];

        $topProductsQuery = SaleItem::query()
            ->whereHas('sale', fn (Builder $query) => $query->where('status', 'completed')->whereBetween('sold_at', [$start, $end]))
            ->select('product_id', 'product_name');

        if ($canViewSales) {
            $topProductsQuery->selectRaw('SUM(quantity) as total_sold, SUM(subtotal) as revenue')
                ->groupBy('product_id', 'product_name')
                ->orderByDesc('total_sold')->orderByDesc('revenue')->orderBy('product_name');
        } else {
            $topProductsQuery->selectRaw('SUM(quantity) as total_sold, 0 as revenue')
                ->groupBy('product_id', 'product_name')
                ->orderByDesc('total_sold')->orderBy('product_name');
        }

        return [
            'range' => $this->dateRange($start, $end),
            'revenueRange' => $this->dateRange($revenueStart, $revenueEnd),
            'activityRange' => $this->dateRange($activityStart, $activityEnd),
            'summary' => $summary,
            'previousSummary' => $previousSummary,
            'changes' => [
                'revenue' => $canViewSales ? $this->percentageChange($summary['revenue'], $previousSummary['revenue']) : null,
                'transactions' => $canViewSales ? $this->percentageChange($summary['transactions'], $previousSummary['transactions']) : null,
            ],
            'revenueSeries' => $canViewSales ? $this->revenueSeries($revenueStart, $revenueEnd) : [],
            'hourlySeries' => $this->hourlySeries($activityStart, $activityEnd),
            'topProducts' => $topProductsQuery->limit(5)->get(),
            'stockOverview' => $this->stockOverview(),
        ];
    }

    /** @return array{count: int, products: Collection<int, Product>} */
    public function stockOverview(): array
    {
        $query = Product::query()
            ->leftJoin('product_stocks', 'products.id', '=', 'product_stocks.product_id')
            ->where('products.is_active', true)
            ->whereRaw('COALESCE(product_stocks.quantity, 0) <= products.stock_minimum');

        return [
            'count' => (clone $query)->count(),
            'products' => $query->select('products.id', 'products.name', 'products.stock_minimum')
                ->selectRaw('COALESCE(product_stocks.quantity, 0) as current_stock')
                ->orderByRaw('COALESCE(product_stocks.quantity, 0) ASC')->orderBy('products.name')
                ->limit(5)->get(),
        ];
    }

    /** @return array{start: string, end: string, label: string} */
    private function dateRange(CarbonImmutable $start, CarbonImmutable $end): array
    {
        return [
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'label' => $start->locale('id')->translatedFormat('j M Y').' - '.$end->locale('id')->translatedFormat('j M Y'),
        ];
    }

    /** @return array{revenue: float, transactions: int} */
    private function salesSummary(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $summary = Sale::query()->where('status', 'completed')->whereBetween('sold_at', [$start, $end])
            ->selectRaw('COUNT(*) as transactions, COALESCE(SUM(grand_total), 0) as revenue')->toBase()->first();

        return ['revenue' => (float) $summary->revenue, 'transactions' => (int) $summary->transactions];
    }

    private function percentageChange(float $current, float $previous): ?float
    {
        if ($previous === 0.0) {
            return $current === 0.0 ? 0.0 : null;
        }

        return round(($current - $previous) / $previous * 100, 1);
    }

    /** @return list<array{date: string, label: string, value: float}> */
    private function revenueSeries(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $totals = Sale::query()->where('status', 'completed')->whereBetween('sold_at', [$start, $end])
            ->selectRaw('DATE(sold_at) as sale_date, SUM(grand_total) as revenue')
            ->groupByRaw('DATE(sold_at)')->toBase()->get()->keyBy('sale_date');
        $series = [];

        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $series[] = [
                'date' => $day->toDateString(),
                'label' => $day->locale('id')->translatedFormat('j M'),
                'value' => (float) ($totals->get($day->toDateString())?->revenue ?? 0),
            ];
        }

        return $series;
    }

    /** @return list<array{label: string, value: int}> */
    private function hourlySeries(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $connection = Sale::query()->getConnection()->getDriverName();
        $hour = match ($connection) {
            'sqlite' => "CAST(strftime('%H', sold_at) AS INTEGER)",
            'pgsql' => 'EXTRACT(HOUR FROM sold_at)',
            default => 'HOUR(sold_at)',
        };
        $totals = Sale::query()->where('status', 'completed')->whereBetween('sold_at', [$start, $end])
            ->selectRaw($hour.' as sale_hour, COUNT(*) as transactions')->groupByRaw($hour)
            ->toBase()->get()->keyBy('sale_hour');
        $hours = $totals->keys()->map(fn (mixed $hour): int => (int) $hour);
        $firstHour = min(8, $hours->min() ?? 8);
        $lastHour = max(14, $hours->max() ?? 14);

        return collect(range($firstHour, $lastHour))->map(fn (int $hour): array => [
            'label' => sprintf('%02d.00', $hour),
            'value' => (int) ($totals->get($hour)?->transactions ?? 0),
        ])->all();
    }
}
