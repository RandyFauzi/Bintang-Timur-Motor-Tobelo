<?php

namespace App\Services;

use App\Models\DistributorInvoice;
use App\Models\Product;
use App\Models\Shipment;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    public const TYPES = ['out_of_stock' => 'Stok Habis', 'shipment_delayed' => 'Pengiriman Terlambat', 'low_stock' => 'Stok Menipis', 'shipment_near' => 'Pengiriman Mendekati Tujuan', 'invoice_overdue' => 'Tagihan Terlambat', 'invoice_due' => 'Tagihan Mendekati Jatuh Tempo'];

    /** @param array<string, mixed> $filters
     * @return array{notifications: LengthAwarePaginator, counts: array<string, int>, total: int}
     */
    public function overview(array $filters): array
    {
        $source = $this->source();
        $totals = DB::query()->fromSub(clone $source, 'alerts')->selectRaw('category, COUNT(*) AS total')->groupBy('category')->pluck('total', 'category');
        $counts = [];
        foreach (array_keys(self::TYPES) as $type) {
            $counts[$type] = (int) ($totals[$type] ?? 0);
        }
        $query = DB::query()->fromSub($source, 'alerts');
        if (! empty($filters['type'])) {
            $query->where('category', $filters['type']);
        }
        if (! empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(fn (Builder $query) => $query->where('title', 'like', $term)->orWhere('context', 'like', $term)->orWhere('reference', 'like', $term)->orWhere('origin', 'like', $term)->orWhere('destination', 'like', $term));
        }

        return [
            'notifications' => $query->orderBy('priority')->orderBy('due_on')->orderBy('title')->orderBy('source_id')->paginate((int) ($filters['per_page'] ?? 15))->withQueryString(),
            'counts' => $counts, 'total' => array_sum($counts),
        ];
    }

    private function source(): Builder
    {
        $stock = Product::query()->leftJoin('product_stocks', 'products.id', '=', 'product_stocks.product_id')->where('products.is_active', true)
            ->whereRaw('COALESCE(product_stocks.quantity, 0) <= products.stock_minimum')
            ->selectRaw("products.id AS source_id, products.name AS title, products.sku AS reference, 'product' AS source, CASE WHEN COALESCE(product_stocks.quantity, 0) <= 0 THEN 'out_of_stock' ELSE 'low_stock' END AS category, CASE WHEN COALESCE(product_stocks.quantity, 0) <= 0 THEN 1 ELSE 3 END AS priority, COALESCE(product_stocks.quantity, 0) AS quantity, products.stock_minimum AS minimum, NULL AS due_on, products.sku AS context, '' AS origin, '' AS destination")
            ->toBase();
        foreach (['delayed' => ['shipment_delayed', 0], 'near_arrival' => ['shipment_near', 2]] as $status => [$category, $priority]) {
            $shipments = Shipment::withStatus($status)->selectRaw("id AS source_id, cargo_name AS title, reference, 'shipment' AS source, ? AS category, ? AS priority, package_count AS quantity, NULL AS minimum, estimated_arrival_on AS due_on, service_name AS context, origin_port AS origin, destination_port AS destination", [$category, $priority])->toBase();
            $stock->unionAll($shipments);
        }

        $today = DistributorInvoice::today()->toDateString();
        $invoices = DistributorInvoice::needsReminder()->join('purchase_orders', 'purchase_orders.id', '=', 'distributor_invoices.purchase_order_id')
            ->selectRaw("distributor_invoices.id AS source_id, purchase_orders.distributor_name AS title, distributor_invoices.reference, 'invoice' AS source, CASE WHEN due_on < ? THEN 'invoice_overdue' ELSE 'invoice_due' END AS category, CASE WHEN due_on < ? THEN 0 ELSE 2 END AS priority, (total_cents - paid_cents) AS quantity, NULL AS minimum, due_on, purchase_orders.reference AS context, '' AS origin, '' AS destination", [$today, $today])->toBase();
        $stock->unionAll($invoices);

        return $stock;
    }
}
