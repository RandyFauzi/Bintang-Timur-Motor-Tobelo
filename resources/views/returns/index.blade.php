<x-app-layout>
    <div class="catalogue-page" x-data="returnSelection(@js(old('selected', [])))">
        <header class="catalogue-heading"><h1>Retur Produk</h1><div class="flex items-center gap-3"><a href="{{ route('returns.activity') }}" class="text-sm underline">Log Aktivitas</a><x-ui.notification-dropdown class="hidden md:inline-flex" /></div></header>
        <div class="catalogue-summary return-summary">
            <div class="catalogue-summary__metric"><span class="text-sm text-muted">Total Produk Retur</span><strong>{{ $summary['products'] }}</strong><span class="text-xs text-muted">Jenis produk yang diretur</span><x-ui.icon name="stock-box" /></div>
            <div class="catalogue-summary__metric"><span class="text-sm text-muted">Total Unit Retur</span><strong>{{ number_format($summary['units']) }}</strong><span class="text-xs text-muted">Total kuantitas retur</span><x-ui.icon name="return-units" /></div>
            <div class="catalogue-summary__chart"><div class="mb-4 flex justify-between gap-4"><h2 class="text-sm text-muted">Status Nota</h2><x-ui.icon name="note-summary" /></div><div class="return-note-chart"><x-ui.category-bar :categories="[['name'=>'Sudah Dikurangi','value'=>$summary['credited']],['name'=>'Belum Dikurangi','value'=>$summary['pending']]]" /></div></div>
        </div>
        <section class="catalogue-surface" aria-label="Daftar retur produk">
            <form method="GET" action="{{ route('returns.index') }}">
                <x-ui.catalogue-toolbar export-route="returns.export" create-label="Tambah Retur" dialog="return-create" />
                <nav class="catalogue-tabs" aria-label="Tipe produk">@foreach([''=>'Semua Produk','daily'=>'Produk Harian','non_daily'=>'Produk Non Harian','variation'=>'Produk Variasi'] as $kind=>$label)<a href="{{ request()->fullUrlWithQuery(['kind'=>$kind ?: null, 'page'=>1,'edit'=>null,'mode'=>null,'create'=>null]) }}" @class(['catalogue-tab','is-active'=>request('kind','') === $kind]) @if(request('kind','') === $kind) aria-current="page" @endif>{{ $label }}</a>@endforeach</nav>
                @if(request()->filled('kind'))<input type="hidden" name="kind" value="{{ request('kind') }}">@endif
                <div class="catalogue-filters return-filters" role="region" aria-label="Filter retur produk">
                    <x-ui.multiselect name="color_ids" label="Semua Warna" :options="$colors" icon="color" />
                    <x-ui.multiselect name="note_status" id="return-note-status" label="Semua Status Nota" :options="collect(['' => 'Semua Status Nota', 'pending' => 'Belum Dikurangi', 'credited' => 'Sudah Dikurangi'])->map(fn (string $label, string $value): object => (object) ['id' => $value, 'name' => $label])->values()" :selected="[request('note_status', '')]" :multiple="false" :dropdown="true" icon="note" />
                    <x-ui.multiselect name="brand_ids" label="Semua Merk" :options="$brands" icon="barcode" />
                    <x-ui.multiselect name="distributor_ids" label="Semua Distributor" :options="$distributors" icon="workshop" />
                    <x-ui.multiselect name="type_ids" label="Semua Part" :options="$types" />
                    <x-ui.multiselect name="status" id="return-status" label="Status Retur" :options="collect(['active' => 'Retur Aktif', 'cancelled' => 'Sudah Dibatalkan', 'all' => 'Semua Riwayat'])->map(fn (string $label, string $value): object => (object) ['id' => $value, 'name' => $label])->values()" :selected="[request('status', 'active')]" :neutral-values="['active']" :multiple="false" :dropdown="true" icon="status-filter" />
                    @if(count(request()->except(['view','page','edit','mode','create'])) > 0)<a href="{{ route('returns.index',['view'=>request('view','list')]) }}" class="filter-reset">Reset filter</a>@endif
                </div>
            </form>
            @if(request('view') === 'cards')
                <div class="catalogue-card-grid">@forelse($returns as $record)
                    @php($snapshot = $record->product_snapshot)
                    <article class="product-card">
                        <header><div class="flex items-start gap-3">@if($record->status === 'active')<input type="checkbox" class="mt-1 rounded" :checked="has(@js($record->id))" @change="toggle(@js($record->id),{{ $record->version }},$event.target.checked)" aria-label="Pilih {{ $snapshot['name'] }}">@endif<div><h2>{{ $snapshot['name'] }}</h2><p class="mt-2 text-sm text-muted">{{ $snapshot['color'] ?: '–' }}</p></div></div>@include('returns.partials.actions')</header>
                        <dl class="product-card__details">
                            <dt>Status Nota</dt><dd><x-ui.return-note-status :value="$record->note_status" /></dd><dt>Jumlah Retur</dt><dd>{{ $record->quantity }}</dd>
                            <dt>Modal</dt><dd>Rp {{ number_format($snapshot['purchase_price'],0,',','.') }}</dd><dt>Total Modal</dt><dd>Rp {{ number_format($record->totalPurchaseCost(),0,',','.') }}</dd>
                            <dt>Tipe Produk</dt><dd>{{ \App\Models\Product::KINDS[$snapshot['kind']] ?? '–' }}</dd><dt>Merk</dt><dd>{{ $snapshot['brand'] ?: '–' }}</dd><dt>Jenis Part</dt><dd>{{ $snapshot['type'] ?: '–' }}</dd><dt>Distributor</dt><dd>{{ $snapshot['distributors'] ?: '–' }}</dd>
                        </dl>
                        @if($record->status === 'active')<div class="flex flex-wrap gap-3"><a href="{{ request()->fullUrlWithQuery(['edit'=>$record->id,'mode'=>'edit','create'=>null]) }}" class="ui-button ui-button--secondary">Edit Jumlah <x-ui.icon name="edit" :size="18" /></a><a href="{{ request()->fullUrlWithQuery(['edit'=>$record->id,'mode'=>'status','create'=>null]) }}" class="ui-button ui-button--secondary">Ubah Nota</a></div>@else<p class="text-sm text-muted">Retur dibatalkan</p>@endif
                    </article>
                @empty<x-ui.empty-state title="Retur tidak ditemukan" description="Tambahkan retur baru atau ubah pilihan filter." />@endforelse</div>
            @else
                @php($pageRows = $returns->where('status','active')->map(fn($record) => ['id'=>$record->id,'version'=>$record->version])->values())
                <div class="catalogue-table-scroll"><table class="catalogue-table return-table"><caption class="sr-only">Daftar retur produk dan status nota</caption>
                    <thead><tr><th scope="col"><input type="checkbox" class="rounded" :checked="pageSelected(@js($pageRows))" @change="togglePage(@js($pageRows), $event.target.checked)" aria-label="Pilih semua retur pada halaman ini"></th>@foreach(['Info Produk','Status Nota','Jumlah Retur','Modal','Total Modal','Tipe Produk','Merk','Jenis Part','Distributor','Aksi'] as $heading)<th scope="col">{{ $heading }}</th>@endforeach</tr></thead>
                    <tbody>@forelse($returns as $record)@php($snapshot = $record->product_snapshot)<tr>
                        <td>@if($record->status === 'active')<input type="checkbox" class="rounded" :checked="has(@js($record->id))" @change="toggle(@js($record->id),{{ $record->version }},$event.target.checked)" aria-label="Pilih {{ $snapshot['name'] }}">@endif</td>
                        <td><span class="font-medium">{{ $snapshot['name'] }}</span><span class="mt-1 block text-muted">{{ $snapshot['color'] ?: '–' }}</span>@if($record->status === 'cancelled')<span class="mt-1 block text-xs text-muted">Retur dibatalkan</span>@endif</td>
                        <td>@if($record->status === 'active')<a href="{{ request()->fullUrlWithQuery(['edit'=>$record->id,'mode'=>'status','create'=>null]) }}" aria-label="Ubah status nota {{ $snapshot['name'] }}"><x-ui.return-note-status :value="$record->note_status"><x-ui.icon name="chevron" :size="12" /></x-ui.return-note-status></a>@else<x-ui.return-note-status :value="$record->note_status" />@endif</td>
                        <td><div class="flex items-center gap-3"><span class="neutral-badge">{{ $record->quantity }}</span>@if($record->status === 'active')<a href="{{ request()->fullUrlWithQuery(['edit'=>$record->id,'mode'=>'edit','create'=>null]) }}" aria-label="Edit jumlah retur {{ $snapshot['name'] }}"><x-ui.icon name="edit" :size="18" /></a>@endif</div></td>
                        <td class="whitespace-nowrap">Rp {{ number_format($snapshot['purchase_price'],0,',','.') }}</td><td class="whitespace-nowrap">Rp {{ number_format($record->totalPurchaseCost(),0,',','.') }}</td><td>{{ \App\Models\Product::KINDS[$snapshot['kind']] ?? '–' }}</td><td><span class="neutral-badge">{{ $snapshot['brand'] ?: '–' }}</span></td><td>{{ $snapshot['type'] ?: '–' }}</td><td>{{ $snapshot['distributors'] ?: '–' }}</td><td>@include('returns.partials.actions')</td>
                    </tr>@empty<tr><td colspan="11"><x-ui.empty-state title="Retur tidak ditemukan" description="Tambahkan retur baru atau ubah pilihan filter." /></td></tr>@endforelse</tbody>
                </table></div>
            @endif
            <div x-cloak x-show="selected.length" class="return-bulk-bar"><span x-text="selected.length + ' Terpilih'"></span><button type="button" class="ui-button" @click="$dispatch('open-dialog',{id:'return-bulk'})"><x-ui.icon name="edit" :size="18" />Edit Massal</button><button type="button" class="text-sm underline" @click="selected=[]">Batal Pilih</button></div>
            <x-ui.pagination :paginator="$returns" />
        </section>
        @include('returns.partials.dialogs')
    </div>
</x-app-layout>
