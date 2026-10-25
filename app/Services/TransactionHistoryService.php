<?php

namespace App\Services;

use App\Models\Sale;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class TransactionHistoryService
{
    /** @param array<string, mixed> $filters */
    public function query(array $filters): Builder
    {
        $query = Sale::query();

        $search = trim($filters['search'] ?? '');
        if ($search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $term = '%'.$search.'%';
                $query->where('invoice_number', 'like', $term)
                    ->orWhere('customer_name', 'like', $term)
                    ->orWhere('customer_phone', 'like', $term)
                    ->orWhereHas('items', fn (Builder $items) => $items
                        ->where('product_name', 'like', $term)->orWhere('sku', 'like', $term)->orWhere('barcode', 'like', $term));
            });
        }

        if (! empty($filters['start_date'])) {
            $query->where('sold_at', '>=', CarbonImmutable::parse($filters['start_date'])->startOfDay());
        }
        if (! empty($filters['end_date'])) {
            $query->where('sold_at', '<', CarbonImmutable::parse($filters['end_date'])->addDay()->startOfDay());
        }
        foreach (['status', 'payment_status'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        if (! empty($filters['user_id'])) {
            $query->whereIn('user_id', (array) $filters['user_id']);
        }
        if (! empty($filters['payment_method'])) {
            $query->whereHas('payments', fn (Builder $payments) => $payments->where('method', $filters['payment_method']));
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{transactions: int, completed: int, cancelled: int, revenue: float}
     */
    public function summary(array $filters): array
    {
        $summary = $this->query($filters)->selectRaw(
            'COUNT(*) as transactions, COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as completed, COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as cancelled, COALESCE(SUM(CASE WHEN status = ? THEN grand_total ELSE 0 END), 0) as revenue',
            ['completed', 'cancelled', 'completed']
        )->toBase()->first();

        return [
            'transactions' => (int) $summary->transactions,
            'completed' => (int) $summary->completed,
            'cancelled' => (int) $summary->cancelled,
            'revenue' => (float) $summary->revenue,
        ];
    }

    /** @param array<string, mixed> $filters */
    public function listing(array $filters): Builder
    {
        return $this->query($filters)->with(['user:id,name', 'payments', 'items'])
            ->withSum('items', 'quantity')->orderByDesc('sold_at')->orderByDesc('id');
    }
}
