<x-app-layout>
    <div class="catalogue-page plan-page">
        <header class="plan-heading"><a class="plan-back" href="{{ route('purchase-orders.index') }}">← Kembali ke daftar Pre Order</a><h1>{{ $plan->name }}</h1><p>{{ $plan->reference }} · {{ $plan->planned_on->translatedFormat('d M Y') }} · {{ $plan->status === 'draft' ? 'Draft' : 'Rencana Difinalisasi' }}</p></header>
        @if($plan->status === 'draft')<a class="ui-button w-fit" href="{{ route('purchase-plans.edit', $plan) }}">Lanjutkan Draft</a>@endif
        <section class="plan-panel">
            <header><h2>Ringkasan Pengadaan</h2><p>Kebutuhan produk dan alokasi distributor sesuai rencana yang disimpan.</p></header>
            <div class="plan-summary"><div><span>Produk</span><strong>{{ count($plan->items) }}</strong><x-ui.icon name="stock-box" :size="20" /></div><div><span>Total Kebutuhan Unit</span><strong>{{ number_format(collect($plan->items)->sum('quantity'), 0, ',', '.') }}</strong><x-ui.icon name="stock-box" :size="20" /></div><div><span>Distributor</span><strong>{{ $plan->orders->count() }}</strong><x-ui.icon name="workshop" :size="20" /></div></div>
        </section>
        @foreach($plan->orders as $order)
            <section class="plan-panel">
                <header class="flex flex-wrap items-center justify-between gap-3"><div><h2>{{ $order->distributor_name }}</h2><p class="mt-2">{{ $order->items->count() }} Produk | {{ number_format($order->items->sum('quantity'), 0, ',', '.') }} Unit</p></div><span class="neutral-badge">{{ $order->statusLabel() }}</span></header>
                <div class="plan-table-shell plan-table-scroll"><table class="plan-table"><thead><tr><th scope="col">Produk</th><th scope="col">Qty Order</th><th scope="col">Datang Aktual</th></tr></thead><tbody>@foreach($order->items as $item)<tr><td>{{ $item->product_name }}</td><td>{{ number_format($item->quantity, 0, ',', '.') }} {{ $item->unit }}</td><td>{{ $item->actual_quantity === null ? '-' : number_format($item->actual_quantity, 0, ',', '.') }}</td></tr>@endforeach</tbody></table></div>
                <div class="flex justify-end"><a class="ui-button ui-button--secondary" href="{{ route('purchase-orders.show', $order) }}">Kelola PO<x-ui.icon name="arrow-right" :size="18" /></a></div>
            </section>
        @endforeach
    </div>
</x-app-layout>
