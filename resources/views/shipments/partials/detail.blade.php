@php($state = $selected->trackingState())
<article class="shipping-detail" x-data="{state: @js($state)}" @shipment-updates.window="if ($event.detail.states[{{ $selected->id }}]) state = $event.detail.states[{{ $selected->id }}]">
    <div class="shipping-detail__heading"><div><p class="shipping-eyebrow">DETAIL PENGIRIMAN</p><h2>{{ $selected->cargo_name }}</h2></div><span class="shipping-status" :class="'shipping-status--' + state.status" x-text="state.label">{{ $state['label'] }}</span></div>
    <div class="shipping-meta">
        <div><span>Nomor Resi / Referensi</span><strong>{{ $selected->reference }}</strong></div><div><span>Moda Transportasi</span><strong>Ocean Cargo</strong></div><div><span>Jasa Pengiriman</span><strong>{{ $selected->service_name }}</strong></div><div><span>Estimasi Tiba</span><strong>{{ $selected->estimated_arrival_on->locale('id')->translatedFormat('j F Y') }}</strong></div>
    </div>
    <div class="shipping-route-panel">
        <div class="shipping-route-labels"><div><span>BERANGKAT DARI</span><strong>{{ $selected->origin_port }}</strong></div><x-ui.icon name="ship" :size="28" /><div><span>TUJUAN PENGIRIMAN</span><strong>{{ $selected->destination_port }}</strong></div></div>
        <svg class="shipping-sea" viewBox="0 0 700 175" role="img" aria-label="Skema rute dari {{ $selected->origin_port }} menuju {{ $selected->destination_port }}">
            <path d="M0 10 75 0 130 30 110 65 60 80 75 130 20 160H0Z M700 0 640 10 625 45 655 80 620 130 670 175H700Z" class="shipping-land"/>
            <path d="M130 115C260 165 385 0 570 80" class="shipping-sea-line"/>
            <circle cx="130" cy="115" r="8" fill="#ffffff" stroke="#2563eb" stroke-width="4"/><circle cx="570" cy="80" r="8" fill="#ffffff" stroke="#2563eb" stroke-width="4"/>
            <g transform="translate(326 60)" fill="none" stroke="#2563eb" stroke-width="3" stroke-linejoin="round"><path d="M0 22 24 12 48 22 40 40H8Z M10 17V0h28v17M24 12v28"/></g>
        </svg>
        <p class="shipping-route-caption">Skema rute laut · Progres berdasarkan estimasi waktu</p>
    </div>
    <div class="shipping-progress-heading"><span>Progres estimasi perjalanan</span><strong x-text="state.progress + '%'">{{ $state['progress'] }}%</strong></div><div class="shipping-progress" role="progressbar" aria-label="Progres estimasi perjalanan" :aria-valuenow="state.progress" aria-valuemin="0" aria-valuemax="100"><span :style="'width:' + state.progress + '%'"></span></div>
    <ol class="shipping-timeline">
        <li :class="{'is-complete': state.status !== 'scheduled'}"><span>1</span><strong>Keberangkatan</strong><small>{{ $selected->departed_on->locale('id')->translatedFormat('j M Y') }}</small></li>
        <li :class="{'is-complete': ['in_transit','near_arrival','delayed','delivered'].includes(state.status)}"><span>2</span><strong>Dalam Perjalanan</strong><small>{{ $selected->duration_days }} hari estimasi</small></li>
        <li :class="{'is-complete': ['near_arrival','delayed','delivered'].includes(state.status)}"><span>3</span><strong>Mendekati Tujuan</strong><small>2 hari sebelum estimasi</small></li>
        <li :class="{'is-complete': state.status === 'delivered'}"><span>4</span><strong>Sudah Sampai</strong><small>{{ $selected->delivered_at ? $selected->delivered_at->timezone(config('shipping.timezone'))->locale('id')->translatedFormat('j M Y, H:i') : 'Menunggu konfirmasi' }}</small></li>
    </ol>
    <div x-cloak x-show="state.status === 'delayed'" class="shipping-warning">Estimasi tiba sudah terlewati. Hubungi jasa pengiriman dan konfirmasi jika cargo sudah diterima.</div>
    <div class="shipping-detail-grid">
        <section><h3>Informasi Cargo</h3><dl><dt>Nama Kapal</dt><dd>{{ $selected->vessel_name ?: 'Belum diisi' }}</dd><dt>Jumlah Kemasan</dt><dd>{{ $selected->package_count }} koli</dd><dt>Durasi Estimasi</dt><dd>{{ $selected->duration_days }} hari</dd><dt>Catatan</dt><dd class="whitespace-pre-line">{{ $selected->notes ?: 'Tidak ada catatan' }}</dd></dl></section>
        <section><h3>Alamat Tujuan</h3><p>{{ $selected->destination_address ?: $selected->destination_port }}</p><p class="mt-3 text-xs text-muted">Perhitungan tanggal mengikuti WIT (UTC+9), waktu setempat Tobelo.</p>@if($selected->delivered_at)<p class="mt-4 text-sm">Dikonfirmasi oleh <strong>{{ $selected->receiver?->name ?? 'Pengguna yang sudah dihapus' }}</strong>.</p>@endif</section>
    </div>
    @if(!$selected->delivered_at)
        <footer class="shipping-detail__actions"><a href="{{ route('shipments.index', ['shipment'=>$selected->id,'edit'=>$selected->id]) }}" class="ui-button ui-button--secondary">Edit Pengiriman</a><button type="button" class="ui-button" :disabled="state.status === 'scheduled' || state.status === 'delivered'" @click="$dispatch('open-dialog',{id:'shipment-arrive'})">Konfirmasi Sudah Sampai</button></footer>
    @endif
</article>
