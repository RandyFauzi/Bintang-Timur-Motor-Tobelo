<x-app-layout :stock-overview="$stockOverview">
    <div class="catalogue-page product-catalogue" x-data="productSelection(@js(old('selected', [])))" :class="{ 'has-selection': selected.length > 0 }">
        <header class="catalogue-heading"><h1>Stok & Produk</h1><x-ui.notification-dropdown class="hidden md:inline-flex" /></header>
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
            @include('products.partials.list')
        </section>
        <div x-cloak x-show="selected.length" class="product-selection-dock" :style="{ '--selection-sidebar-width': isMobile ? '0px' : (collapsed ? '80px' : '256px') }">
            <div class="return-bulk-bar" role="group" aria-label="Aksi produk terpilih">
                <span class="product-selection-count text-sm" role="status" aria-live="polite" aria-atomic="true" x-text="selected.length + ' Terpilih'"></span>
                <button type="button" class="ui-button product-selection-edit" aria-label="Edit massal produk terpilih" @click="$dispatch('open-dialog', {id: 'product-bulk-edit'})"><x-ui.icon name="edit" :size="16" /><span class="product-selection-label-full">Edit Massal</span><span class="product-selection-label-short" aria-hidden="true">Edit</span></button>
                <button type="button" class="ui-button ui-button--secondary product-selection-delete" aria-label="Hapus produk terpilih" @click="$dispatch('open-dialog', {id: 'product-bulk-delete'})"><x-ui.icon name="trash" :size="16" /><span class="product-selection-label-full">Hapus Terpilih</span><span class="product-selection-label-short" aria-hidden="true">Hapus</span></button>
                <button type="button" class="ui-button ui-button--secondary product-selection-cancel text-sm" @click="selected = []">Batal Pilih</button>
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
