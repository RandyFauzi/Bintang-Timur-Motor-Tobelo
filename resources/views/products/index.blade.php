<x-app-layout :stock-overview="$stockOverview">
    <div class="catalogue-page">
        <header class="catalogue-heading"><h1>Stok & Produk</h1></header>
        <div class="catalogue-summary">
            <div class="catalogue-summary__metric"><span class="text-sm text-muted">Stok Menipis</span><strong>{{ $stockOverview['count'] }}</strong><span class="text-xs text-muted">Kondisi stok saat ini</span><x-ui.icon name="stock-box" /></div>
            <div class="catalogue-summary__chart"><p class="mb-4 text-sm text-muted">Jenis Part</p><x-ui.category-bar :categories="$categories" /></div>
        </div>
        <section class="catalogue-surface" aria-label="Daftar produk">
            <form method="GET" action="{{ route('products.index') }}" class="catalogue-query">
                <x-ui.catalogue-toolbar export-route="products.export" create-label="Tambah Produk" dialog="product-form" />
                <nav class="catalogue-tabs" aria-label="Tipe produk">
                    @foreach(['' => 'Semua Produk', 'daily' => 'Produk Harian', 'non_daily' => 'Produk Non Harian'] as $kind => $label)
                        <a href="{{ request()->fullUrlWithQuery(['kind'=>$kind ?: null,'page'=>1,'edit'=>null,'create'=>null]) }}" @class(['catalogue-tab','is-active'=>request('kind','') === $kind]) @if(request('kind','') === $kind) aria-current="page" @endif>{{ $label }}</a>
                    @endforeach
                </nav>
                @if(request()->filled('kind'))<input type="hidden" name="kind" value="{{ request('kind') }}">@endif
                <div class="catalogue-filters">
                    <x-ui.multiselect name="color_ids" label="Semua Warna" :options="$colors" />
                    <x-ui.multiselect name="type_ids" label="Semua Jenis Part" :options="$types" />
                    <x-ui.multiselect name="brand_ids" label="Semua Merk" :options="$brands" />
                    <x-ui.multiselect name="distributor_ids" label="Semua Distributor" :options="$distributors" icon="workshop" />
                    <label class="filter-check"><input type="checkbox" name="low_stock" value="1" @checked(request()->boolean('low_stock')) onchange="this.form.requestSubmit()"> Stok menipis</label>
                    @if(count(request()->except(['view','page','edit','create'])) > 0)<a href="{{ route('products.index',['view'=>request('view','list')]) }}" class="filter-reset">Reset filter</a>@endif
                </div>
            </form>
            @if(request('view') === 'cards')
                <div class="catalogue-card-grid">
                    @forelse($products as $product)
                        <article class="product-card">
                            <header><div><h2>{{ $product->name }}</h2><p class="mt-2 text-sm text-muted">{{ $product->color?->name ?? '–' }}</p></div><a href="{{ request()->fullUrlWithQuery(['edit'=>$product->id,'create'=>null]) }}" aria-label="Edit {{ $product->name }}"><x-ui.icon name="edit" :size="18" /></a></header>
                            <dl class="product-card__details">
                                <dt>Total Stok</dt><dd><span @class(['stock-badge'=>($product->stock?->quantity ?? 0) <= $product->stock_minimum])>{{ $product->stock?->quantity ?? 0 }}</span></dd>
                                <dt>Tipe Produk</dt><dd>{{ $product->product_kind === 'daily' ? 'Harian' : 'Non-Harian' }}</dd>
                                <dt>Modal</dt><dd>Rp {{ number_format($product->purchase_price,0,',','.') }}</dd>
                                <dt>Harga Jual</dt><dd>Rp {{ number_format($product->selling_price,0,',','.') }}</dd>
                                <dt>Merk Produk</dt><dd><span class="neutral-badge">{{ $product->brand?->name ?? '–' }}</span></dd>
                                <dt>Jenis Part</dt><dd>{{ $product->type?->name ?? '–' }}</dd>
                                <dt>Distributor</dt><dd>{{ $product->distributors->pluck('name')->join(', ') ?: '–' }}</dd>
                            </dl>
                            <a href="{{ request()->fullUrlWithQuery(['edit'=>$product->id,'create'=>null]) }}" class="ui-button ui-button--secondary w-full">Edit Produk <x-ui.icon name="edit" :size="16" /></a>
                        </article>
                    @empty
                        <x-ui.empty-state title="Produk tidak ditemukan" description="Tambahkan produk baru atau ubah pilihan filter." />
                    @endforelse
                </div>
            @else
                <div class="catalogue-table-scroll"><table class="catalogue-table"><caption class="sr-only">Daftar stok dan produk</caption>
                    <thead><tr>@foreach(['Info Produk','Modal','Harga Jual','Tipe Produk','Merk','Jenis Part','Distributor','Stok','Aksi'] as $heading)<th scope="col">{{ $heading }}</th>@endforeach</tr></thead>
                    <tbody>@forelse($products as $product)<tr>
                        <td><span class="font-medium">{{ $product->name }}</span><span class="mt-1 block text-muted">{{ $product->color?->name ?? '–' }}</span></td>
                        <td class="whitespace-nowrap">Rp {{ number_format($product->purchase_price,0,',','.') }}</td><td class="whitespace-nowrap">Rp {{ number_format($product->selling_price,0,',','.') }}</td>
                        <td>{{ $product->product_kind === 'daily' ? 'Harian' : 'Non-Harian' }}</td><td><span class="neutral-badge">{{ $product->brand?->name ?? '–' }}</span></td>
                        <td>{{ $product->type?->name ?? '–' }}</td><td>{{ $product->distributors->pluck('name')->join(', ') ?: '–' }}</td>
                        <td><span @class(['stock-badge'=>($product->stock?->quantity ?? 0) <= $product->stock_minimum])>{{ $product->stock?->quantity ?? 0 }}</span></td>
                        <td><a href="{{ request()->fullUrlWithQuery(['edit'=>$product->id,'create'=>null]) }}" class="edit-link">Edit <x-ui.icon name="edit" :size="16" /></a></td>
                    </tr>@empty<tr><td colspan="9"><x-ui.empty-state title="Produk tidak ditemukan" description="Tambahkan produk baru atau ubah pilihan filter." /></td></tr>@endforelse</tbody>
                </table></div>
            @endif
            <x-ui.pagination :paginator="$products" />
        </section>
        @include('products.partials.form', ['product' => $editing])
    </div>
</x-app-layout>
