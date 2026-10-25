<x-app-layout :stock-overview="$stockOverview">
    <div class="catalogue-page product-catalogue" x-data="productSelection(@js(old('selected', [])))" :class="{ 'has-selection': selected.length > 0 }">
        @php($pageProductIds = $products->modelKeys())
        <header class="catalogue-heading"><h1>Stok & Produk</h1></header>
        <div class="catalogue-summary">
            <div class="catalogue-summary__metric"><span class="text-sm text-muted">Stok Menipis</span><strong>{{ $stockOverview['count'] }}</strong><span class="text-xs text-muted">Kondisi stok saat ini</span><x-ui.icon name="stock-box" /></div>
            <div class="catalogue-summary__chart"><p class="mb-4 text-sm text-muted">Jenis Part</p><x-ui.category-bar :categories="$categories" /></div>
        </div>
        <section class="catalogue-surface" aria-label="Daftar produk">
            <form method="GET" action="{{ route('products.index') }}" class="catalogue-query">
                <x-ui.catalogue-toolbar export-route="products.export" create-label="Tambah Produk" dialog="product-form" />
                <nav class="catalogue-tabs" aria-label="Tipe produk">
                    @foreach(['' => 'Semua Produk', 'daily' => 'Produk Harian', 'non_daily' => 'Produk Non Harian', 'variation' => 'Produk Variasi'] as $kind => $label)
                        <a href="{{ request()->fullUrlWithQuery(['kind'=>$kind ?: null,'page'=>1,'edit'=>null,'create'=>null]) }}" @class(['catalogue-tab','is-active'=>request('kind','') === $kind]) @if(request('kind','') === $kind) aria-current="page" @endif>{{ $label }}</a>
                    @endforeach
                </nav>
                @if(request()->filled('kind'))<input type="hidden" name="kind" value="{{ request('kind') }}">@endif
                <div class="catalogue-filters catalogue-filters--scroll" role="region" aria-label="Filter produk, geser untuk melihat pilihan lainnya" tabindex="0">
                    <x-ui.multiselect name="color_ids" label="Semua Warna" :options="$colors" />
                    <x-ui.multiselect name="type_ids" label="Semua Part" :options="$types" />
                    <x-ui.multiselect name="brand_ids" label="Semua Merk" :options="$brands" />
                    <x-ui.multiselect name="distributor_ids" label="Semua Distributor" :options="$distributors" icon="workshop" />
                    <x-ui.multiselect name="low_stock" label="Semua Stok" :options="[(object)['id'=>'','name'=>'Semua Stok'],(object)['id'=>'1','name'=>'Stok Menipis']]" :selected="[request('low_stock','')]" :multiple="false" icon="filter-stock" />
                    @if(count(request()->except(['view','page','edit','create'])) > 0)<a href="{{ route('products.index',['view'=>request('view','list')]) }}" class="filter-reset">Reset filter</a>@endif
                </div>
            </form>
            @if(request('view') === 'cards')
                <div class="catalogue-card-grid">
                    @forelse($products as $product)
                        <article class="product-card">
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="rounded" :checked="has({{ $product->id }})" @change="toggle({{ $product->id }}, $event.target.checked)" aria-label="Pilih {{ $product->name }}">Pilih produk</label>
                            <header><div><h2>{{ $product->name }}</h2><p class="mt-2 text-sm text-muted">{{ $product->color?->name ?? '–' }}</p></div><div class="product-card__actions">
                                <x-ui.product-actions :product="$product" />
                            </div></header>
                            <dl class="product-card__details">
                                <dt>Total Stok</dt><dd><span @class(['stock-badge'=>($product->stock?->quantity ?? 0) <= $product->stock_minimum])>{{ $product->stock?->quantity ?? 0 }}</span></dd>
                                <dt>Tipe Produk</dt><dd>{{ \App\Models\Product::KINDS[$product->product_kind] ?? '–' }}</dd>
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
                <div class="catalogue-table-scroll"><table class="catalogue-table catalogue-table--selectable"><caption class="sr-only">Daftar stok dan produk</caption>
                    <thead><tr><th scope="col"><input type="checkbox" class="rounded" :checked="pageSelected(@js($pageProductIds))" x-effect="$el.indeterminate = @js($pageProductIds).some(id => has(id)) && !pageSelected(@js($pageProductIds))" @change="togglePage(@js($pageProductIds), $event.target.checked)" aria-label="Pilih semua produk pada halaman ini"></th>@foreach(['Info Produk','Modal','Harga Jual','Tipe Produk','Merk','Jenis Part','Distributor','Stok','Aksi'] as $heading)<th scope="col">{{ $heading }}</th>@endforeach</tr></thead>
                    <tbody>@forelse($products as $product)<tr>
                        <td><input type="checkbox" class="rounded" :checked="has({{ $product->id }})" @change="toggle({{ $product->id }}, $event.target.checked)" aria-label="Pilih {{ $product->name }}"></td>
                        <td><span class="font-medium">{{ $product->name }}</span><span class="mt-1 block text-muted">{{ $product->color?->name ?? '–' }}</span></td>
                        <td class="whitespace-nowrap">Rp {{ number_format($product->purchase_price,0,',','.') }}</td><td class="whitespace-nowrap">Rp {{ number_format($product->selling_price,0,',','.') }}</td>
                        <td>{{ \App\Models\Product::KINDS[$product->product_kind] ?? '–' }}</td><td><span class="neutral-badge">{{ $product->brand?->name ?? '–' }}</span></td>
                        <td>{{ $product->type?->name ?? '–' }}</td><td>{{ $product->distributors->pluck('name')->join(', ') ?: '–' }}</td>
                        <td><span @class(['stock-badge'=>($product->stock?->quantity ?? 0) <= $product->stock_minimum])>{{ $product->stock?->quantity ?? 0 }}</span></td>
                        <td><div class="flex items-center gap-2"><a href="{{ request()->fullUrlWithQuery(['edit'=>$product->id,'create'=>null]) }}" class="edit-link">Edit</a><x-ui.product-actions :product="$product" /></div></td>
                    </tr>@empty<tr><td colspan="10"><x-ui.empty-state title="Produk tidak ditemukan" description="Tambahkan produk baru atau ubah pilihan filter." /></td></tr>@endforelse</tbody>
                </table></div>
            @endif
            <x-ui.pagination :paginator="$products" />
        </section>
        <div x-cloak x-show="selected.length" class="product-selection-dock" :style="{ '--selection-sidebar-width': isMobile ? '0px' : (collapsed ? '80px' : '256px') }">
            <div class="return-bulk-bar" role="group" aria-label="Aksi produk terpilih">
                <span class="product-selection-count text-sm" role="status" aria-live="polite" aria-atomic="true" x-text="selected.length + ' Terpilih'"></span>
                <button type="button" class="ui-button product-selection-edit" @click="$dispatch('open-dialog', {id: 'product-bulk-edit'})"><x-ui.icon name="edit" :size="16" />Edit Massal</button>
                <button type="button" class="ui-button ui-button--secondary product-selection-delete" @click="$dispatch('open-dialog', {id: 'product-bulk-delete'})"><x-ui.icon name="trash" :size="16" />Hapus Terpilih</button>
                <button type="button" class="product-selection-cancel text-sm underline" @click="selected = []">Batal Pilih</button>
            </div>
        </div>
        @include('products.partials.form', ['product' => $editing])
        @include('products.partials.bulk')
        <x-ui.dialog name="product-delete" title="Hapus Produk" description="Produk akan dihapus dari katalog. Riwayat transaksi dan retur tetap tersimpan.">
            <form method="POST" class="dialog-form" x-data="{ productName: '', deleteUrl: '' }" :action="deleteUrl" @delete-product.window="productName = $event.detail.name; deleteUrl = $event.detail.url; $dispatch('open-dialog', { id: 'product-delete' })" @submit="submitting = true">
                @csrf @method('DELETE')
                <p class="text-sm">Hapus produk <strong x-text="productName"></strong>?</p>
                <footer class="dialog-actions"><button type="button" class="ui-button ui-button--cancel" @click="$el.closest('dialog').close()">Batalkan</button><button type="submit" class="ui-button" :disabled="submitting || !deleteUrl" x-text="submitting ? 'Menghapus…' : 'Hapus Produk'">Hapus Produk</button></footer>
            </form>
        </x-ui.dialog>
    </div>
</x-app-layout>
