<x-app-layout>
    <div class="catalogue-page pos-register" x-data="posRegister(@js($catalogueProducts), @js(['scan'=>route('pos.scan'),'checkout'=>route('pos.checkout')]), @js('pos-cart:'.auth()->id()))" @keydown.window="shortcut($event)">
        <header class="catalogue-heading"><h1>Kasir / POS</h1><span class="text-xs text-muted">F1 Cari · F4 Keranjang</span></header>
        <section class="catalogue-surface" aria-label="Pilih produk">
            <form method="GET" action="{{ route('pos.index') }}">
                <div class="catalogue-toolbar">
                    <label class="catalogue-search"><span class="sr-only">Cari produk atau scan barcode</span><x-ui.icon name="search" :size="16" /><input type="search" name="search" value="{{ request('search') }}" x-ref="search" placeholder="Cari produk, SKU, atau scan barcode..." maxlength="200" @keydown.enter="if ($event.target.value.trim() && /^\d{6,}$/.test($event.target.value.trim())) { $event.preventDefault(); scan($event.target.value); }"><button type="submit" class="sr-only">Cari</button></label>
                    <div class="catalogue-toolbar__actions pos-cart-launcher"><button type="button" class="ui-button pos-cart-trigger" @click="openCart()" aria-controls="pos-cart"><x-ui.icon name="pos-cart" :size="18" />Lihat Keranjang <span class="pos-cart-count" x-cloak x-show="units" x-text="units" :aria-label="units + ' item di keranjang'" aria-live="polite" aria-atomic="true"></span></button></div>
                </div>
                @if(request()->filled('per_page'))<input type="hidden" name="per_page" value="{{ request('per_page') }}">@endif
                <div class="catalogue-filters">
                    <x-ui.multiselect name="color_ids" label="Semua Warna" :options="$colors" icon="color" />
                    <x-ui.multiselect name="brand_ids" label="Semua Merk" :options="$brands" icon="barcode" />
                    <x-ui.multiselect name="distributor_ids" label="Semua Distributor" :options="$distributors" icon="workshop" />
                    <button type="button" class="text-sm underline" @click="scan($refs.search.value)" :disabled="scanning || busy" x-text="scanning ? 'Memindai…' : 'Scan Barcode'">Scan Barcode</button>
                    @if(count(request()->except(['page','per_page'])) > 0)<a href="{{ route('pos.index') }}" class="filter-reset">Reset filter</a>@endif
                </div>
            </form>
            <div class="pos-products">
                <template x-for="product in products" :key="product.id">
                    <article class="pos-product">
                        <header><div class="min-w-0 flex-1"><div class="pos-product__name"><x-ui.icon name="pos-box" :size="18" /><h2 class="min-w-0 truncate font-semibold" x-text="product.name" :title="product.name"></h2></div><p class="mt-2 text-sm text-muted" x-text="product.color || '–'"></p></div><span x-show="product.brand" class="pos-product__brand" x-text="product.brand" :title="product.brand"></span></header>
                        <dl><dt>Total Stok</dt><dd><span :class="product.available <= product.minimum ? 'stock-badge' : ''" x-text="product.available"></span></dd><dt>Jenis Part</dt><dd x-text="product.type || '–'"></dd><dt>Distributor</dt><dd x-text="product.distributors || '–'"></dd></dl>
                        <footer><strong x-text="money(product.price)"></strong><div class="pos-product__cart-action"><span class="text-xs font-medium text-muted whitespace-nowrap" x-cloak x-show="quantity(product.id) > 0" x-text="quantity(product.id) + ' item'" :aria-label="quantity(product.id) + ' item ' + product.name + ' di keranjang'" aria-live="polite" aria-atomic="true"></span><button type="button" class="ui-button pos-add" :disabled="busy || quantity(product.id) >= product.available" @click="add(product)" :aria-label="'Tambah '+product.name+' ke keranjang'"><x-ui.icon name="pos-cart" :size="18" /></button></div></footer>
                    </article>
                </template>
                @if($products->isEmpty())<div class="col-span-full"><x-ui.empty-state title="Produk tidak ditemukan" description="Ubah pencarian atau filter untuk memilih produk lainnya." /></div>@endif
            </div>
            <x-ui.pagination :paginator="$products" />
        </section>
        @include('pos.partials.cart')
        @include('pos.partials.review')
    </div>
</x-app-layout>
