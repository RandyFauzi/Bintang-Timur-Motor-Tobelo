@props(['endpoint', 'initial' => null, 'required' => true])
<div x-data="productPicker(@js($endpoint), @js($initial))" x-init="load()" class="grid gap-3">
    <label for="product-search" class="form-field__label">Cari Produk</label>
    <label class="catalogue-search"><input id="product-search" type="search" x-model="search" @input.debounce.300ms="load()" placeholder="Tulis nama produk atau SKU..." aria-describedby="product-search-help"><x-ui.icon name="search" :size="16" /></label>
    <p id="product-search-help" class="text-xs text-muted">Pilih produk dari hasil pencarian di bawah. Maksimal 20 hasil ditampilkan.</p>
    <x-ui.field name="product_id" label="Produk yang Diretur">
        <select id="product_id" name="product_id" x-model="selected" class="form-input" @required($required) :aria-busy="busy">
            <option value="">Pilih produk</option>
            <template x-for="product in products" :key="product.id"><option :value="String(product.id)" x-text="product.name + ' · ' + product.sku + ' · stok tersedia ' + product.available"></option></template>
        </select>
    </x-ui.field>
    <p x-show="busy" role="status" class="text-xs text-muted">Memuat produk...</p>
    <p x-cloak x-show="!busy && !products.length && !error" class="text-sm text-muted">Produk tidak ditemukan. Ubah kata pencarian.</p>
    <p x-cloak x-show="error" x-text="error" role="alert" class="field-error"></p>
    {{ $slot }}
</div>
