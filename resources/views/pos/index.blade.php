<x-app-layout>
    <div class="catalogue-page pos-register" x-data="posRegister(@js($catalogueProducts), @js(['scan'=>route('pos.scan'),'checkout'=>route('pos.checkout'),'nextPageUrl'=>$products->nextPageUrl()]), @js('pos-cart:'.auth()->id()))" @keydown.window="shortcut($event)">
        <header class="catalogue-heading"><h1>Kasir / POS</h1><div class="flex items-center gap-3"><span class="text-xs text-muted">F1 Cari · F4 Keranjang</span><x-ui.notification-dropdown /></div></header>
        <section class="catalogue-surface" aria-label="Pilih produk">
            <form method="GET" action="{{ route('pos.index') }}">
                <div class="catalogue-toolbar">
                    <label class="catalogue-search"><span class="sr-only">Cari produk atau scan barcode</span><x-ui.icon name="search" :size="16" /><input type="search" name="search" value="{{ request('search') }}" x-ref="search" placeholder="Cari produk, SKU, atau scan barcode..." maxlength="200" @keydown.enter="if ($event.target.value.trim() && /^\d{6,}$/.test($event.target.value.trim())) { $event.preventDefault(); scan($event.target.value); }"><button type="submit" class="sr-only">Cari</button></label>
                    <div class="catalogue-toolbar__actions pos-cart-launcher"><button type="button" class="ui-button pos-cart-trigger" @click="openCart()" aria-controls="pos-cart"><x-ui.icon name="pos-cart" :size="18" />Lihat Keranjang <span class="pos-cart-count" x-cloak x-show="units" x-text="units" :aria-label="units + ' item di keranjang'" aria-live="polite" aria-atomic="true"></span></button></div>
                </div>
                @if(request()->filled('per_page'))<input type="hidden" name="per_page" value="{{ request('per_page') }}">@endif
                <div class="catalogue-filters catalogue-filters--scroll" role="region" aria-label="Filter kasir, geser untuk melihat pilihan lainnya" tabindex="0">
                    <x-ui.multiselect name="color_ids" label="Semua Warna" :options="$colors" icon="color" />
                    <x-ui.multiselect name="brand_ids" label="Semua Merk" :options="$brands" icon="barcode" />
                    <button type="button" class="text-sm underline" @click="scan($refs.search.value)" :disabled="scanning || busy" x-text="scanning ? 'Memindai…' : 'Scan Barcode'">Scan Barcode</button>
                    @if(count(request()->except(['page','per_page'])) > 0)<a href="{{ route('pos.index') }}" class="filter-reset">Reset filter</a>@endif
                </div>
            </form>
            <div class="pos-products">
                <template x-for="product in products" :key="product.id">
                    <article class="pos-product" :class="{'pos-product--in-cart': quantity(product.id) > 0, 'pos-product--clickable': product.available > 0}" @click="onProductClick(product)">
                        <header><div class="pos-product__name"><x-ui.icon name="pos-box" :size="18" class="shrink-0 mt-0.5 text-muted" /><h2 class="pos-product__title" x-text="product.name" :title="product.name"></h2></div></header>
                        <dl>
                            <dt>Merek</dt><dd x-text="product.brand || '–'"></dd>
                            <dt>Distributor</dt><dd x-text="product.distributor || '–'"></dd>
                            <dt>Stok</dt><dd><span :class="product.available <= product.minimum ? 'stock-badge' : ''" x-text="product.available"></span></dd>
                            <div class="contents" x-show="product.color"><dt>Warna</dt><dd x-text="product.color"></dd></div>
                        </dl>
                        <footer>
                            <strong x-text="money(product.price)"></strong>
                            <div class="pos-product__cart-action" @click.stop>
                                <button type="button" class="ui-button pos-add" x-show="quantity(product.id) === 0" :disabled="busy || product.available < 1" @click.stop="add(product)" :aria-label="'Tambah '+product.name+' ke keranjang'"><x-ui.icon name="pos-cart" :size="18" /></button>
                                <div class="pos-stepper" x-show="quantity(product.id) > 0" x-cloak role="group" :aria-label="'Ubah jumlah ' + product.name">
                                    <button type="button" class="pos-stepper__btn pos-stepper__btn--minus" @click.stop="decrement(product.id)" :disabled="busy" :aria-label="'Kurangi '+product.name">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                                    </button>
                                    <input type="number" min="1" :max="product.available" step="1" :value="quantity(product.id)" :disabled="busy" @input="updateQuantityInput(product.id, $event.target)" @change="updateQuantityInput(product.id, $event.target, true)" @click.stop class="pos-stepper__input" :aria-label="'Jumlah '+product.name">
                                    <button type="button" class="pos-stepper__btn pos-stepper__btn--plus" @click.stop="add(product)" :disabled="busy || quantity(product.id) >= product.available" :aria-label="'Tambah '+product.name">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                                    </button>
                                </div>
                            </div>
                        </footer>
                    </article>
                </template>
                @if($products->isEmpty())<div class="col-span-full"><x-ui.empty-state title="Produk tidak ditemukan" description="Ubah pencarian atau filter untuk memilih produk lainnya." /></div>@endif
            </div>
            <div class="pos-infinite-scroll mobile-infinite-scroll md:hidden"
                 x-show="window.innerWidth <= 767"
                 x-init="
                    if (window.innerWidth <= 767 && hasMore) {
                        const obs = new IntersectionObserver((entries) => {
                            if (entries[0].isIntersecting && hasMore && !loadingMore && window.innerWidth <= 767) {
                                loadMore();
                            }
                        }, { rootMargin: '250px' });
                        obs.observe($refs.posSentinel);
                    }
                 ">
                <div x-show="loadingMore" class="infinite-scroll-loading" aria-live="polite">
                    <span class="infinite-scroll-spinner" aria-hidden="true"></span>
                    <span>Memuat produk...</span>
                </div>
                <div x-ref="posSentinel" class="h-4 w-full" x-show="hasMore && !loadingMore" aria-hidden="true"></div>
                <div x-show="!hasMore && products.length > 0 && @js($products->total() > 6)" class="infinite-scroll-end">
                    Semua produk telah dimuat ({{ $products->total() }} produk)
                </div>
            </div>
            <x-ui.pagination :paginator="$products" :infinite="false" />
        </section>
        @include('pos.partials.cart')
        @include('pos.partials.review')
    </div>
</x-app-layout>
