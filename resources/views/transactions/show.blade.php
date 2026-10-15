<x-app-layout>
    <div class="catalogue-page">
        <header class="catalogue-heading flex-wrap">
            <div><a href="{{ route('transactions.index') }}" class="text-sm underline">Kembali ke Histori Transaksi</a><h1 class="mt-3">Detail Transaksi</h1><p class="mt-2 break-all text-sm text-muted">{{ $sale->invoice_number }}</p></div>
            <div class="flex flex-wrap gap-2"><x-ui.transaction-status :value="$sale->status" /><x-ui.transaction-status :value="$sale->payment_status" payment /></div>
        </header>
        @if($sale->status === 'cancelled')<p class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-danger">Transaksi ini dibatalkan dan tidak dihitung dalam nilai penjualan selesai.</p>@endif
        <div class="mb-6 grid gap-4 md:grid-cols-3">
            @foreach([['Waktu Transaksi', $sale->sold_at?->format('d/m/Y H:i:s') ?? 'Tanggal tidak tersedia'], ['Pelanggan', $sale->customer_name ?: 'Pelanggan Umum'], ['Kasir', $sale->user?->name ?? '–']] as [$label,$value])
                <section class="catalogue-surface p-5"><h2 class="text-sm text-muted">{{ $label }}</h2><p class="mt-2 break-words font-medium">{{ $value }}</p>@if($label === 'Pelanggan' && $sale->customer_phone)<p class="mt-2 text-sm text-muted">{{ $sale->customer_phone }}</p>@endif</section>
            @endforeach
        </div>
        <section class="catalogue-surface mb-6" aria-label="Item transaksi">
            <div class="p-5"><h2 class="font-semibold">Produk yang Dibeli</h2><p class="mt-2 text-xs text-muted">Nama dan harga di bawah disimpan saat transaksi berlangsung.</p></div>
            <div class="catalogue-table-scroll"><table class="catalogue-table"><caption class="sr-only">Rincian produk pada invoice {{ $sale->invoice_number }}</caption>
                <thead><tr>@foreach(['Produk','SKU / Barcode','Jumlah','Harga Satuan','Diskon Item','Subtotal'] as $heading)<th scope="col">{{ $heading }}</th>@endforeach</tr></thead>
                <tbody>@forelse($sale->items as $item)<tr><td class="font-medium">{{ $item->product_name }}</td><td>{{ $item->sku ?: '–' }}<span class="mt-1 block text-muted">{{ $item->barcode ?: '–' }}</span></td><td>{{ $item->quantity }}</td><td>Rp {{ number_format($item->unit_price,2,',','.') }}</td><td>Rp {{ number_format($item->discount,2,',','.') }}</td><td>Rp {{ number_format($item->subtotal,2,',','.') }}</td></tr>
                @empty<tr><td colspan="6"><x-ui.empty-state title="Rincian item tidak tersedia" description="Data transaksi ini belum memiliki rincian produk." /></td></tr>@endforelse</tbody>
            </table></div>
        </section>
        <div class="grid items-start gap-6 xl:grid-cols-2">
            <section class="catalogue-surface p-5"><h2 class="mb-4 font-semibold">Riwayat Pembayaran</h2>
                <div class="grid gap-4">@forelse($sale->payments as $payment)
                    <article class="rounded-xl border border-gray-200 p-4"><div class="flex flex-wrap justify-between gap-3"><h3 class="font-medium">{{ $payment->method === 'cash' ? 'Tunai' : ucfirst($payment->method) }}</h3><strong>Rp {{ number_format($payment->amount,2,',','.') }}</strong></div><p class="mt-2 text-sm text-muted">{{ $payment->paid_at?->format('d/m/Y H:i:s') ?? 'Waktu pembayaran tidak tersedia' }}</p>@if($payment->reference_number)<p class="mt-2 break-words text-sm">Referensi: {{ $payment->reference_number }}</p>@endif</article>
                @empty<p class="text-sm text-muted">Belum ada catatan pembayaran.</p>@endforelse</div>
            </section>
            <section class="catalogue-surface p-5"><h2 class="mb-4 font-semibold">Ringkasan Belanja</h2>
                <dl class="grid grid-cols-2 gap-x-4 gap-y-4 text-sm">
                    @foreach(['Subtotal (setelah diskon item)'=>'subtotal','Diskon Transaksi'=>'discount','Pajak'=>'tax','Total Belanja'=>'grand_total','Uang Diterima'=>'paid_amount','Kembalian'=>'change_amount'] as $label=>$field)
                        <dt @class(['font-semibold' => $field === 'grand_total'])>{{ $label }}</dt><dd @class(['text-right', 'font-semibold' => $field === 'grand_total'])>Rp {{ number_format($sale->$field,2,',','.') }}</dd>
                    @endforeach
                </dl>
                <p class="mt-5 border-t border-gray-200 pt-4 text-xs text-muted">Nilai riwayat pembayaran adalah pembayaran yang dialokasikan ke belanja. Uang diterima dapat lebih besar jika ada kembalian.</p>
            </section>
        </div>
    </div>
</x-app-layout>
