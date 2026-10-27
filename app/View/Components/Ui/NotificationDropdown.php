<?php

namespace App\View\Components\Ui;

use App\Services\NotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\View\Component;
use Illuminate\View\View;

class NotificationDropdown extends Component
{
    /** @var Collection<int, array<string, mixed>> */
    public Collection $items;

    public int $total;

    public function __construct(NotificationService $notificationService)
    {
        $overview = $notificationService->overview(['per_page' => 10]);
        $this->total = (int) $overview['total'];

        $this->items = collect($overview['notifications']->items())->map(function (object $item): array {
            $key = $item->source.'_'.$item->source_id.'_'.$item->category;
            $url = match ($item->source) {
                'product' => route('products.index', ['edit' => $item->source_id]),
                'invoice' => route('distributor-invoices.show', $item->source_id),
                'shipment' => route('shipments.index', ['shipment' => $item->source_id]).'#shipment-detail',
                default => route('notifications.index'),
            };

            $urgent = (int) $item->priority <= 1;

            if ($item->source === 'product') {
                $qty = (int) $item->quantity;
                $min = (int) $item->minimum;
                $message = $item->category === 'out_of_stock'
                    ? 'Stok produk sudah habis (0 unit).'
                    : "Sisa {$qty} unit · Stok minimum {$min} unit.";
                $subtitle = 'SKU: '.$item->reference;
            } elseif ($item->source === 'invoice') {
                $rupiah = 'Rp '.number_format($item->quantity / 100, 0, ',', '.');
                $dueDate = $item->due_on ? CarbonImmutable::parse($item->due_on)->format('d/m/Y') : '–';
                $message = $item->category === 'invoice_overdue'
                    ? "Lewat jatuh tempo ({$dueDate}) · Sisa {$rupiah}"
                    : "Jatuh tempo {$dueDate} · Sisa {$rupiah}";
                $subtitle = $item->reference.($item->context ? ' · '.$item->context : '');
            } else {
                $estDate = $item->due_on ? CarbonImmutable::parse($item->due_on)->locale('id')->translatedFormat('j M Y') : '–';
                $message = $item->category === 'shipment_delayed'
                    ? "Estimasi tiba terlewati ({$estDate})"
                    : "Estimasi tiba: {$estDate}";
                $subtitle = 'Resi '.$item->reference.($item->origin && $item->destination ? " · {$item->origin} → {$item->destination}" : '');
            }

            return [
                'key' => $key,
                'title' => $item->title,
                'source' => $item->source,
                'category' => $item->category,
                'category_label' => NotificationService::TYPES[$item->category] ?? 'Notifikasi',
                'urgent' => $urgent,
                'message' => $message,
                'subtitle' => $subtitle,
                'url' => $url,
                'action_label' => match ($item->source) {
                    'product' => 'Perbarui Stok',
                    'invoice' => 'Lihat Tagihan',
                    'shipment' => 'Lihat Pengiriman',
                    default => 'Lihat Detail',
                },
            ];
        });
    }

    public function render(): View
    {
        return view('components.ui.notification-dropdown');
    }
}
