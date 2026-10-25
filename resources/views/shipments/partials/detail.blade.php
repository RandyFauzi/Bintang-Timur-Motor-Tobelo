@php($state = $selected->trackingState())
<article class="shipping-detail" x-data="{state: @js($state)}" @shipment-updates.window="if ($event.detail.states[{{ $selected->id }}]) state = $event.detail.states[{{ $selected->id }}]">
    <div class="shipping-detail__heading"><div><p class="shipping-eyebrow">DETAIL PENGIRIMAN</p><h2>{{ $selected->cargo_name }}</h2></div><span class="shipping-status" :class="'shipping-status--' + state.status" x-text="state.label">{{ $state['label'] }}</span></div>
    <div class="shipping-meta">
        <div><span>Nomor Resi / Referensi</span><strong>{{ $selected->reference }}</strong></div><div><span>Moda Transportasi</span><strong>Ocean Cargo</strong></div><div><span>Jasa Pengiriman</span><strong>{{ $selected->service_name }}</strong></div><div><span>Estimasi Tiba</span><strong>{{ $selected->estimated_arrival_on->locale('id')->translatedFormat('j F Y') }}</strong></div>
    </div>
    @include('shipments.partials.voyage')
    <div class="shipping-progress-heading"><span>Progres estimasi perjalanan</span><strong x-text="state.progress + '%'">{{ $state['progress'] }}%</strong></div><div class="shipping-progress" role="progressbar" aria-label="Progres estimasi perjalanan" :aria-valuenow="state.progress" aria-valuemin="0" aria-valuemax="100"><span :style="'width:' + state.progress + '%'"></span></div>
    <ol class="shipping-timeline">
        <li :class="{'is-complete': state.status !== 'scheduled'}"><span>1</span><strong>Keberangkatan</strong><small>{{ $selected->departed_on->locale('id')->translatedFormat('j M Y') }}</small></li>
        <li :class="{'is-complete': ['in_transit','near_arrival','delayed','delivered'].includes(state.status)}"><span>2</span><strong>Dalam Perjalanan</strong><small>{{ $selected->duration_days }} hari estimasi</small></li>
        <li :class="{'is-complete': ['near_arrival','delayed','delivered'].includes(state.status)}"><span>3</span><strong>Mendekati Tujuan</strong><small>2 hari sebelum estimasi</small></li>
        <li :class="{'is-complete': state.status === 'delivered'}"><span>4</span><strong>Sudah Sampai</strong><small>{{ $selected->delivered_at ? $selected->delivered_at->timezone(config('shipping.timezone'))->locale('id')->translatedFormat('j M Y, H:i') : 'Menunggu konfirmasi' }}</small></li>
    </ol>
    <div x-cloak x-show="state.status === 'delayed'" class="shipping-warning">Estimasi tiba sudah terlewati. Hubungi jasa pengiriman dan konfirmasi jika cargo sudah diterima.</div>
    <div class="shipping-detail-grid">
        <section><h3>Informasi Cargo</h3><dl><dt>Nama Kapal</dt><dd>{{ $selected->vessel_name ?: 'Belum diisi' }}</dd>@if($selected->distributorInvoices->isNotEmpty())<dt>Distributor</dt><dd>{{ $selected->distributorInvoices->pluck('purchaseOrder.distributor_name')->unique()->join(', ') }}</dd>@endif<dt>Jumlah Kemasan</dt><dd>{{ $selected->package_count }} koli</dd><dt>Ongkir Ekspedisi</dt><dd>{{ $selected->shipping_cost_cents > 0 ? $selected->formattedShippingCost() : 'Belum diisi / Bebas Ongkir' }}</dd><dt>Durasi Estimasi</dt><dd>{{ $selected->duration_days }} hari</dd><dt>Catatan</dt><dd class="whitespace-pre-line">{{ $selected->notes ?: 'Tidak ada catatan' }}</dd></dl></section>
        <section><h3>Alamat Tujuan</h3><p>{{ $selected->destination_address ?: $selected->destination_port }}</p><p class="mt-3 text-xs text-muted">Perhitungan tanggal mengikuti WIT (UTC+9), waktu setempat Tobelo.</p>@if($selected->delivered_at)<p class="mt-4 text-sm">Dikonfirmasi oleh <strong>{{ $selected->receiver?->name ?? 'Pengguna yang sudah dihapus' }}</strong>.</p>@endif</section>
    </div>
    <section class="mt-5 rounded-xl border p-4"><h3 class="font-medium">Pesanan per Distributor</h3><p class="mt-2 text-xs text-muted">Jumlah barang mengikuti nota yang divalidasi. Konfirmasi Sudah Sampai menjadi tanggal masuk pada tagihan.</p>
        <div class="mt-3 grid gap-4">
            @forelse($selected->distributorInvoices as $cargoInvoice)
                <article class="min-w-0 rounded-xl border bg-gray-50 p-4">
                    <header class="flex flex-wrap items-start justify-between gap-3"><div class="min-w-0"><h4 class="break-words font-medium">{{ $cargoInvoice->purchaseOrder->distributor_name }}</h4><p class="mt-1 break-words text-xs text-muted">PO {{ $cargoInvoice->purchaseOrder->reference }} · Nota {{ $cargoInvoice->supplier_reference ?: '-' }}</p></div><a class="text-sm underline" href="{{ route('distributor-invoices.show', $cargoInvoice) }}">Lihat Tagihan</a></header>
                    <p class="mt-2 break-words text-xs text-muted">Tagihan {{ $cargoInvoice->reference }}</p>
                    <ul class="mt-3 divide-y">
                        @foreach($cargoInvoice->purchaseOrder->items as $cargoItem)
                            @if(($cargoItem->invoice_quantity ?? $cargoItem->quantity) > 0)<li class="flex items-start justify-between gap-4 py-3 text-sm"><span class="min-w-0 break-words">{{ $cargoItem->product_name }}</span><strong class="shrink-0 font-medium">{{ $cargoItem->invoice_quantity ?? $cargoItem->quantity }} {{ $cargoItem->unit }}</strong></li>@endif
                        @endforeach
                    </ul>
                    <div class="mt-3 flex flex-wrap justify-between gap-2 border-t pt-3 text-sm"><span>Total Nota: Rp {{ number_format($cargoInvoice->total_cents/100,2,',','.') }}</span><span>Sisa: Rp {{ number_format($cargoInvoice->remainingCents()/100,2,',','.') }}</span></div>
                </article>
            @empty<p class="text-sm text-muted">Belum ada faktur terhubung. Pilih kargo ini dari menu Edit Faktur & Tempo pada detail tagihan.</p>@endforelse
        </div>
    </section>
    @if(!$selected->delivered_at)
        <footer class="shipping-detail__actions"><a href="{{ route('shipments.index', ['shipment'=>$selected->id,'edit'=>$selected->id]) }}" class="ui-button ui-button--secondary">Edit Pengiriman</a><button type="button" class="ui-button" :disabled="state.status === 'scheduled' || state.status === 'delivered'" @click="$dispatch('open-dialog',{id:'shipment-arrive'})">Konfirmasi Sudah Sampai</button></footer>
    @endif
</article>
