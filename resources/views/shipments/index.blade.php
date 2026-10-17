<x-app-layout>
    <div class="catalogue-page shipping-page" x-init="$dispatch('watch-shipments', {ids: @js($shipments->pluck('id')->push($selected?->id)->filter()->unique()->values())})" x-data="{ counts: @js($counts) }" @shipment-updates.window="counts = $event.detail.counts">
        <header class="shipping-heading">
            <div><p class="shipping-eyebrow">LOGISTIK LAUT</p><h1>Pelacak Pengiriman</h1><p class="mt-2 text-sm text-muted">Pantau perjalanan cargo menuju toko, dari keberangkatan hingga diterima.</p></div>
            <div class="flex flex-wrap gap-2"><button type="button" class="ui-button ui-button--secondary" @click="$dispatch('open-dialog',{id:'shipping-services'})">Jasa Pengiriman</button><button type="button" class="ui-button" @click="$dispatch('open-dialog',{id:'shipment-form'})"><x-ui.icon name="add" :size="18" />Tambah Pengiriman</button></div>
        </header>
        <div class="shipping-metrics">
            @foreach(['in_transit'=>'Sedang Berjalan', 'near_arrival'=>'Mendekati Tujuan', 'delayed'=>'Terlambat', 'delivered'=>'Sudah Sampai'] as $key=>$label)
                <a href="{{ route('shipments.index', ['status'=>$key]) }}" class="shipping-metric shipping-metric--{{ $key }}"><span class="shipping-dot"></span><span class="text-sm text-muted">{{ $label }}</span><strong x-text="counts.{{ $key }}">{{ $counts[$key] }}</strong></a>
            @endforeach
        </div>
        <section class="shipping-workspace" aria-label="Pelacakan cargo">
            <aside class="shipping-list">
                <div class="shipping-list__heading"><h2 class="font-medium">Daftar Pengiriman</h2><span class="text-xs text-muted">{{ $shipments->total() }} pengiriman</span></div>
                <form method="GET" action="{{ route('shipments.index') }}" class="grid gap-3">
                    <label class="catalogue-search !w-full !min-w-0"><x-ui.icon name="search" :size="18" /><input type="search" name="search" value="{{ request('search') }}" placeholder="Cari cargo, resi, kapal…" aria-label="Cari pengiriman"></label>
                    <x-ui.multiselect name="status" id="shipping-status" label="Semua Status" :options="collect([''=>'Semua Status'] + \App\Models\Shipment::STATUSES)->map(fn (string $label, string $value): object => (object)['id'=>$value,'name'=>$label])->values()" :selected="[request('status','')]" :multiple="false" icon="status-filter" />
                    <button type="submit" class="ui-button ui-button--secondary w-full">Cari Pengiriman</button>
                </form>
                <div class="shipping-list__items">
                    @forelse($shipments as $item)
                        @php($itemState = $item->trackingState())
                        <a href="{{ request()->fullUrlWithQuery(['shipment'=>$item->id, 'edit'=>null, 'service_edit'=>null]) }}#shipment-detail" @class(['shipping-list-card', 'is-selected'=>$selected?->id === $item->id]) @if($selected?->id === $item->id) aria-current="true" @endif x-data="{state: @js($itemState)}" @shipment-updates.window="if ($event.detail.states[{{ $item->id }}]) state = $event.detail.states[{{ $item->id }}]">
                            <div class="flex flex-wrap items-center justify-between gap-2"><span class="shipping-reference">{{ $item->reference }}</span><span class="shipping-status" :class="'shipping-status--' + state.status" x-text="state.label">{{ $itemState['label'] }}</span></div>
                            <h3 class="mt-3 break-words font-medium">{{ $item->cargo_name }}</h3><p class="mt-1 text-xs text-muted">{{ $item->service_name }}</p>
                            <div class="shipping-mini-route"><span>{{ $item->origin_port }}</span><span aria-hidden="true">→</span><span>{{ $item->destination_port }}</span></div>
                            <div class="flex justify-between gap-2 text-xs text-muted"><span>{{ $item->package_count }} koli</span><span>Estimasi {{ $item->estimated_arrival_on->locale('id')->translatedFormat('j M Y') }}</span></div>
                        </a>
                    @empty
                        <x-ui.empty-state title="Belum ada pengiriman" description="Tambahkan cargo baru atau ubah pencarian." />
                    @endforelse
                </div>
                {{ $shipments->links() }}
            </aside>
            <div class="shipping-main" id="shipment-detail">
                @if($selected)
                    @include('shipments.partials.detail')
                @else
                    <div class="shipping-welcome"><x-ui.icon name="ship" :size="64" /><h2>Perjalanan cargo dimulai di sini</h2><p>Tambahkan jasa pengiriman dan durasi estimasinya, lalu catat pengiriman pertama menuju toko.</p><button type="button" class="ui-button" @click="$dispatch('open-dialog',{id:'shipment-form'})">Tambah Pengiriman</button></div>
                @endif
            </div>
        </section>
        @include('shipments.partials.dialogs')
    </div>
</x-app-layout>
