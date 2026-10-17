@props(['endpoint', 'initial' => null, 'required' => true])
<div x-data="productPicker(@js($endpoint), @js($initial))" x-init="load()" x-id="['product-search', 'product-results', 'product-help']" class="grid min-w-0 gap-3">
    <label :for="$id('product-search')" class="form-field__label">Cari dan Pilih Produk</label>
    <div class="catalogue-search !w-full !min-w-0">
        <input x-ref="search" :id="$id('product-search')" type="search" x-model="search" @focus="open = true" @input="onSearchInput()" @input.debounce.300ms="load()" @keydown.escape.stop="open = false" placeholder="Nama, SKU, merk, jenis part, tipe, warna…" :aria-describedby="$id('product-help')" :aria-controls="$id('product-results')" :aria-expanded="open" autocomplete="off" x-effect="$refs.search.setCustomValidity(@js($required) && !choice ? 'Pilih produk dari daftar hasil pencarian.' : '')">
        <x-ui.icon name="search" :size="16" />
    </div>
    <input type="hidden" name="product_id" :value="selected">
    <p :id="$id('product-help')" class="text-xs text-muted">Cari berdasarkan satu atau beberapa kata. Pilih langsung dari daftar katalog Stok &amp; Produk.</p>
    <div x-cloak x-show="open" :id="$id('product-results')" class="max-h-64 overflow-y-auto overscroll-contain rounded-xl border border-gray-200" :aria-busy="busy">
        <p x-show="busy" role="status" class="p-3 text-sm text-muted">Memuat produk…</p>
        <template x-for="product in products" :key="product.id">
            <button type="button" @click="select(product)" class="flex w-full min-w-0 items-start justify-between gap-3 border-b border-gray-100 p-3 text-left text-sm last:border-b-0 hover:bg-gray-50 focus-visible:bg-gray-100 focus-visible:outline-none" :aria-pressed="String(product.id) === selected">
                <span class="min-w-0"><span class="block break-words font-medium" x-text="product.name"></span><span class="mt-1 block break-words text-xs text-muted" x-text="description(product)"></span></span>
                <span class="shrink-0 rounded-lg bg-gray-100 px-2 py-1 text-xs" x-text="'Stok ' + product.available"></span>
            </button>
        </template>
        <p x-show="!busy && !products.length && !error" role="status" class="p-3 text-sm text-muted">Produk tidak ditemukan. Coba kata kunci lain.</p>
        <p x-show="!busy && products.length" class="p-3 text-xs text-muted">Menampilkan hingga 20 produk. Persempit pencarian untuk hasil lainnya.</p>
    </div>
    <div x-cloak x-show="choice && !open" class="rounded-xl border border-gray-200 bg-gray-50 p-3 text-sm">
        <p class="font-medium" x-text="choice?.name"></p>
        <p class="mt-1 break-words text-xs text-muted" x-text="choice ? description(choice) : ''"></p>
        <p class="mt-2 text-xs" x-text="'Stok tersedia: ' + (choice?.available ?? 0)"></p>
    </div>
    @error('product_id')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    <p x-cloak x-show="error" x-text="error" role="alert" class="field-error"></p>
    {{ $slot }}
</div>
