<x-app-layout>
    <div class="catalogue-page notification-page">
        <header class="notification-heading"><div><h1>Notifikasi</h1><p>Semua yang perlu ditindaklanjuti, dalam satu halaman.</p></div><a href="{{ request()->fullUrl() }}" class="ui-button ui-button--secondary">Perbarui Notifikasi</a></header>
        <div class="notification-overview"><div><span class="notification-overview__dot" aria-hidden="true"></span><strong>{{ $total }}</strong><span>notifikasi aktif</span></div><p>Prioritas utama ditampilkan lebih dulu. Notifikasi akan hilang saat kondisinya sudah ditangani.</p></div>
        <div class="notification-metrics">
            @foreach(\App\Services\NotificationService::TYPES as $type=>$label)
                <a href="{{ route('notifications.index',['type'=>$type]) }}" @class(['notification-metric', 'notification-metric--urgent'=>in_array($type,['out_of_stock','shipment_delayed'])])><span>{{ $label }}</span><strong>{{ $counts[$type] }}</strong></a>
            @endforeach
        </div>
        <section class="catalogue-surface" aria-label="Daftar notifikasi aktif">
            <form method="GET" action="{{ route('notifications.index') }}" class="notification-filters">
                <label class="catalogue-search !min-w-0"><x-ui.icon name="search" :size="18"/><input type="search" name="search" value="{{ request('search') }}" placeholder="Cari produk, cargo, ekspedisi, atau resi…" aria-label="Cari notifikasi"></label>
                <x-ui.multiselect name="type" label="Semua Jenis" :options="collect([''=>'Semua Jenis'] + \App\Services\NotificationService::TYPES)->map(fn (string $label, string $value): object => (object)['id'=>$value,'name'=>$label])->values()" :selected="[request('type','')]" :multiple="false" icon="status-filter" />
                <button type="submit" class="ui-button ui-button--secondary">Cari</button>
                @if(request()->filled('type') || request()->filled('search'))<a href="{{ route('notifications.index') }}" class="filter-reset">Reset filter</a>@endif
            </form>
            <div class="notification-list">
                @forelse($notifications as $notification)
                    @php($urgent = (int) $notification->priority <= 1)
                    <article @class(['notification-item','notification-item--urgent'=>$urgent])>
                        <span class="notification-item__icon" aria-hidden="true"><x-ui.icon :name="$notification->source === 'product' ? 'stock-box' : 'ship'" :size="24" /></span>
                        <div class="notification-item__body">
                            <div class="notification-item__labels"><span class="notification-kind">{{ \App\Services\NotificationService::TYPES[$notification->category] }}</span>@if($urgent)<span class="notification-priority">Perlu tindakan</span>@endif</div>
                            <h2>{{ $notification->title }}</h2>
                            @if($notification->source === 'product')
                                <p>{{ $notification->category === 'out_of_stock' ? 'Stok produk sudah habis.' : 'Stok produk mencapai batas minimum.' }} Sisa <strong>{{ (int) $notification->quantity }}</strong> unit · Stok minimum {{ (int) $notification->minimum }} unit.</p>
                                <small>SKU: {{ $notification->reference }} · Perbarui stok setelah barang tersedia.</small>
                            @else
                                <p>{{ $notification->context }} · {{ $notification->origin }} → {{ $notification->destination }}</p>
                                <small>Resi {{ $notification->reference }} · Estimasi tiba {{ \Carbon\CarbonImmutable::parse($notification->due_on)->locale('id')->translatedFormat('j M Y') }} (WIT)</small>
                                <p class="notification-item__hint">{{ $notification->category === 'shipment_delayed' ? 'Estimasi tiba terlewati. Hubungi ekspedisi atau konfirmasi jika barang sudah diterima.' : 'Cargo diperkirakan tiba dalam 2 hari atau kurang. Siapkan penerimaan barang.' }}</p>
                            @endif
                        </div>
                        <a href="{{ $notification->source === 'product' ? route('products.index',['edit'=>$notification->source_id]) : route('shipments.index',['shipment'=>$notification->source_id]).'#shipment-detail' }}" class="ui-button ui-button--secondary notification-item__action">{{ $notification->source === 'product' ? 'Perbarui Stok' : 'Lihat Pengiriman' }}<span class="sr-only">: {{ $notification->title }}</span><span aria-hidden="true">→</span></a>
                    </article>
                @empty
                    <x-ui.empty-state title="Tidak ada notifikasi" description="Semua kondisi sudah aman, atau tidak ada hasil yang sesuai dengan filter Anda." />
                @endforelse
            </div>
            <x-ui.pagination :paginator="$notifications" />
        </section>
        <p class="notification-note">Halaman ini menampilkan kondisi terkini. Menambah stok atau mengonfirmasi penerimaan cargo akan memperbarui daftar secara otomatis saat halaman dimuat ulang.</p>
    </div>
</x-app-layout>
