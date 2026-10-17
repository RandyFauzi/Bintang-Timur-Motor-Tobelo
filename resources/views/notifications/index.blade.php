<x-app-layout>
    <div class="catalogue-page notification-page" x-data="notificationCenter(@js(request()->fullUrl()), @js($counts), @js($total))" @focus.window="refresh()" @notification-count.window="if ($event.detail.count !== total) refresh()">
        <header class="notification-heading"><div><h1>Notifikasi</h1><p>Semua yang perlu ditindaklanjuti, dalam satu halaman.</p></div></header>
        <div class="notification-overview"><div><span class="notification-overview__dot" aria-hidden="true"></span><strong x-text="total">{{ $total }}</strong><span>notifikasi aktif</span></div><p>Prioritas utama ditampilkan lebih dulu. Notifikasi akan hilang saat kondisinya sudah ditangani.</p></div>
        <div class="notification-metrics">
            @foreach(\App\Services\NotificationService::TYPES as $type=>$label)
                <a href="{{ route('notifications.index',['type'=>$type]) }}" @class(['notification-metric', 'is-active'=>request('type') === $type, 'notification-metric--urgent'=>in_array($type,['out_of_stock','shipment_delayed'])]) @if(request('type') === $type) aria-current="page" @endif>@if(request('type') === $type)<span class="notification-metric__mark" aria-hidden="true">✓</span>@endif<span>{{ $label }}</span><strong x-text="counts[@js($type)]">{{ $counts[$type] }}</strong></a>
            @endforeach
        </div>
        <section class="catalogue-surface" aria-label="Daftar notifikasi aktif">
            <form method="GET" action="{{ route('notifications.index') }}" class="notification-filters">
                <label class="catalogue-search !min-w-0"><x-ui.icon name="search" :size="18"/><input type="search" name="search" value="{{ request('search') }}" placeholder="Cari produk, cargo, ekspedisi, atau resi…" aria-label="Cari notifikasi"></label>
                <x-ui.multiselect name="type" label="Semua Jenis" :options="collect([''=>'Semua Jenis'] + \App\Services\NotificationService::TYPES)->map(fn (string $label, string $value): object => (object)['id'=>$value,'name'=>$label])->values()" :selected="[request('type','')]" :multiple="false" icon="status-filter" />
                <button type="submit" class="ui-button ui-button--secondary">Cari</button>
                @if(request()->filled('type') || request()->filled('search'))<a href="{{ route('notifications.index') }}" class="filter-reset">Reset filter</a>@endif
            </form>
            <div x-ref="feed">@include('notifications.partials.feed')</div>
        </section>
    </div>
</x-app-layout>
