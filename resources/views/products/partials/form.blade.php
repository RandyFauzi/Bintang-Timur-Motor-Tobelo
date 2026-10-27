@php
    $detailsExpanded = ! $product || (old('_dialog') === 'product-form' && old('_edit_details') === '1');
    $currentDistributor = $product?->distributors->first();
    $initialDistributorId = old('distributor_id', old('distributor_ids.0', $currentDistributor?->id));
    $initialDistributorName = old('distributor_name', $currentDistributor?->name ?? ($initialDistributorId ? $distributors->firstWhere('id', $initialDistributorId)?->name : ''));
    $distributorItems = $distributors->merge($product?->distributors ?? collect())->unique('id')->sortBy('name')->map(fn ($d) => ['id' => (string) $d->id, 'name' => $d->name])->values();
@endphp
<x-ui.dialog name="product-form" class="product-form-dialog" :wide="true" :title="$product ? 'Edit '.$product->name : 'Tambah Produk'" :description="$product ? 'Edit jumlah stok atau detail informasi produk.' : 'Tambahkan produk baru beserta stok awalnya.'" :show="$product !== null || request()->boolean('create') || old('_dialog') === 'product-form'">
    <div x-data="{ tab: @js(! $product && old('_tab') === 'import' ? 'import' : 'manual') }" class="grid gap-5">
        @if(! $product)
            <div class="catalogue-tabs !m-0 !p-0" role="tablist" aria-label="Cara tambah produk">
                <button id="product-manual-tab" type="button" role="tab" class="catalogue-tab" :class="{ 'is-active': tab === 'manual' }" :aria-selected="tab === 'manual'" :tabindex="tab === 'manual' ? 0 : -1" aria-controls="product-manual-panel" @click="tab = 'manual'" @keydown.arrow-right.prevent="tab = 'import'; $refs.importTab.focus()" x-ref="manualTab">Input Manual</button>
                <button id="product-import-tab" type="button" role="tab" class="catalogue-tab" :class="{ 'is-active': tab === 'import' }" :aria-selected="tab === 'import'" :tabindex="tab === 'import' ? 0 : -1" aria-controls="product-import-panel" @click="tab = 'import'" @keydown.arrow-left.prevent="tab = 'manual'; $refs.manualTab.focus()" x-ref="importTab">Import Excel</button>
            </div>
        @endif
        <div id="product-manual-panel" x-show="tab === 'manual'" @if(! $product) role="tabpanel" aria-labelledby="product-manual-tab" @endif>
    <form method="POST" action="{{ $product ? route('products.update',$product) : route('products.store') }}" class="dialog-form" x-data="{ detailsExpanded: @js($detailsExpanded) }" @submit="submitting = true">
        @csrf @if($product) @method('PATCH') @endif
        <input type="hidden" name="_dialog" value="product-form">
        <input type="hidden" name="_edit_details" :value="detailsExpanded ? '1' : '0'" value="{{ $detailsExpanded ? '1' : '0' }}">
        <fieldset id="product-edit-details" class="form-grid min-w-0" x-show="detailsExpanded" :disabled="!detailsExpanded" @disabled(! $detailsExpanded) @if(! $detailsExpanded) x-cloak @endif>
            <legend class="sr-only">Detail informasi produk</legend>
            <x-ui.field name="name" label="Nama Produk" :value="$product?->name" required maxlength="255" />
            <x-ui.field name="brand_name" label="Merk" :value="$product?->brand?->name" list="brand-options" placeholder="Tulis atau cari merk" maxlength="255" />
            <x-ui.field name="type_name" label="Jenis Part" :value="$product?->type?->name" list="type-options" placeholder="Tulis atau cari jenis part" maxlength="255" />
            <x-ui.field name="color_name" label="Warna" :value="$product?->color?->name" list="color-options" placeholder="Tulis atau cari warna" maxlength="255" />
            <x-ui.field name="purchase_price" label="Modal" type="number" :value="$product ? (float) $product->purchase_price : null" placeholder="0" min="0" step="0.01" onfocus="this.select()" required />
            <x-ui.field name="selling_price" label="Harga Jual" type="number" :value="$product ? (float) $product->selling_price : null" placeholder="0" min="0" step="0.01" onfocus="this.select()" required />
            <x-ui.field name="product_kind" label="Tipe Produk"><select id="product_kind" name="product_kind" class="form-input">@foreach(\App\Models\Product::KINDS as $value => $kindLabel)<option value="{{ $value }}" @selected(old('product_kind',$product?->product_kind ?? 'daily') === $value)>{{ $kindLabel }}</option>@endforeach</select></x-ui.field>
            <div class="form-field relative product-distributor-picker" x-data="distributorAutocomplete(@js($distributorItems), @js($initialDistributorId), @js($initialDistributorName), @js(route('distributors.store')))" @click.outside="close()" @keydown.escape="close()">
                <label for="distributor_name" class="form-field__label">Distributor</label>
                <div class="relative">
                    <div class="relative flex items-center">
                        <span class="pointer-events-none absolute left-3 flex items-center text-muted">
                            <x-ui.icon name="workshop" :size="18" />
                        </span>
                        <input
                            id="distributor_name"
                            name="distributor_name"
                            type="text"
                            class="form-input !pl-10 !pr-10 w-full"
                            placeholder="Tulis atau cari nama distributor..."
                            autocomplete="off"
                            x-model="query"
                            @focus="openDropdown()"
                            @input="onInput()"
                            @keydown.arrow-down.prevent="onArrowDown()"
                            @keydown.arrow-up.prevent="onArrowUp()"
                            @keydown.enter="onEnter($event)"
                            maxlength="255"
                        >
                        <input type="hidden" name="distributor_id" :value="selectedId">
                        <input type="hidden" name="_sync_distributors" value="1">
                        <template x-if="selectedId || query.trim()">
                            <button
                                type="button"
                                class="absolute right-3 flex items-center text-muted hover:text-stone-900"
                                @click="clear()"
                                title="Kosongkan distributor"
                                aria-label="Kosongkan distributor"
                            >
                                <x-ui.icon name="close" :size="16" />
                            </button>
                        </template>
                    </div>

                    <div
                        x-show="open"
                        x-cloak
                        x-transition.opacity
                        class="distributor-autocomplete-menu"
                        role="listbox"
                        tabindex="-1"
                    >
                        <div class="max-h-60 overflow-y-auto py-1">
                            <template x-for="(item, index) in filtered" :key="item.id">
                                <button
                                    type="button"
                                    class="distributor-autocomplete-item"
                                    :class="{ 'is-highlighted': highlightedIndex === index, 'is-selected': selectedId == item.id }"
                                    @click="select(item)"
                                    @mouseenter="highlightedIndex = index"
                                    role="option"
                                    :aria-selected="selectedId == item.id"
                                >
                                    <div class="flex items-center gap-2 min-w-0">
                                        <x-ui.icon name="workshop" :size="16" class="text-muted shrink-0" />
                                        <span class="truncate font-medium" x-text="item.name"></span>
                                    </div>
                                    <span x-show="selectedId == item.id" class="text-xs text-green-700 font-semibold" aria-hidden="true">✓</span>
                                </button>
                            </template>

                            <template x-if="filtered.length === 0 && !canCreate">
                                <p class="px-3 py-2 text-xs text-muted">Belum ada data distributor.</p>
                            </template>

                            <template x-if="canCreate">
                                <div class="border-t border-stone-100 p-1">
                                    <button
                                        type="button"
                                        class="distributor-autocomplete-add"
                                        :class="{ 'is-highlighted': highlightedIndex === filtered.length }"
                                        @click="createNew()"
                                        :disabled="isAdding"
                                    >
                                        <span class="flex items-center gap-2 min-w-0">
                                            <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-green-100 text-green-700 font-bold text-xs">+</span>
                                            <span class="truncate">Tambah distributor: <strong class="text-stone-900" x-text="'&ldquo;' + query.trim() + '&rdquo;'"></strong></span>
                                        </span>
                                        <span x-show="isAdding" class="text-xs text-muted shrink-0">Menyimpan...</span>
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <template x-if="newlyAdded">
                    <p class="mt-1 flex items-center gap-1.5 text-xs text-green-700 font-medium">
                        <svg class="size-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                        <span>Distributor baru telah didaftarkan dan terpilih.</span>
                    </p>
                </template>
                @error('distributor_name')<p class="field-error">{{ $message }}</p>@enderror
                @error('distributor_ids')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <x-ui.field name="barcode" label="Barcode (opsional)" :value="$product?->barcode" maxlength="100" />
            @if($product)<div class="form-field"><span class="form-field__label">SKU</span><p class="py-2 text-sm text-muted">{{ $product->sku }}</p></div>@else<x-ui.field name="sku" label="SKU (opsional)" hint="Dibuat otomatis jika dikosongkan." maxlength="100" />@endif
        </fieldset>
        <div class="form-grid">
            <x-ui.field name="quantity" label="Jumlah Stok" type="number" :value="$product ? $product->stock?->quantity : null" placeholder="0" min="0" max="100000000" onfocus="this.select()" required />
            <x-ui.field name="stock_minimum" label="Stok Minimum" type="number" :value="$product ? $product->stock_minimum : null" placeholder="0" min="0" max="100000000" onfocus="this.select()" required />
        </div>
        @if($product)
            <button type="button" class="edit-link justify-self-start" @click="detailsExpanded = !detailsExpanded" :aria-expanded="detailsExpanded" aria-controls="product-edit-details">
                <span x-text="detailsExpanded ? 'Tutup Detail' : 'Edit Detail'">Edit Detail</span><x-ui.icon name="chevron" :size="16" x-bind:class="{ 'rotate-90': detailsExpanded }" />
            </button>
        @endif
        @foreach(['brand'=>'brands','type'=>'types','color'=>'colors'] as $name=>$collection)<datalist id="{{ $name }}-options">@foreach($$collection as $option)<option value="{{ $option->name }}">@endforeach</datalist>@endforeach
        <footer class="dialog-actions"><button type="button" class="ui-button ui-button--cancel" @click="$el.closest('dialog').close()">Batalkan</button><button type="submit" class="ui-button" :disabled="submitting" x-text="submitting ? 'Menyimpan…' : 'Simpan'">Simpan</button></footer>
    </form>
        </div>
        @if(! $product)
            <form id="product-import-panel" role="tabpanel" aria-labelledby="product-import-tab" x-show="tab === 'import'" x-cloak method="POST" action="{{ route('products.import') }}" enctype="multipart/form-data" class="dialog-form" @submit="submitting = true">
                @csrf
                <input type="hidden" name="_dialog" value="product-form"><input type="hidden" name="_tab" value="import">
                <p class="text-sm text-muted">Import file Excel dengan urutan kolom bebas, tanpa batas jumlah baris. Ukuran file maksimal 5 MB.</p>
                <x-ui.field name="file" label="File Excel (.xlsx)" type="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required hint="Pilih ulang file jika validasi gagal." />
                <ul class="list-disc pl-5 text-sm text-muted space-y-2">
                    <li>ID produk yang sama memperbarui data. ID kosong menambah produk baru; SKU kosong dibuat otomatis. ID yang tidak ditemukan ditolak.</li>
                    <li>Isi angka tanpa Rp atau pemisah ribuan; desimal memakai titik. Kolom yang tidak ada di file tetap dipertahankan untuk produk lama. Kolom opsional yang kosong akan dikosongkan.</li>
                    <li>Tipe Produk: Harian, Non-Harian, atau Variasi. Maksimal satu distributor per baris. Untuk distributor berbeda, gunakan baris dan ID produk berbeda. Nama distributor yang belum terdaftar akan dibuat otomatis.</li>
                    <li>Kolom opsional menerima sel kosong, NULL, atau tanda -. Tipe Produk kosong menjadi Harian untuk produk baru; tipe produk lama tetap dipertahankan.</li>
                    <li>Format kolom SKU dan Barcode sebagai Teks agar angka nol di depan tidak hilang.</li>
                    <li>Semua baris diperiksa terlebih dahulu. Jika ada kesalahan, seluruh import dibatalkan.</li>
                </ul>
                <details class="rounded-xl border border-gray-200 p-4">
                    <summary class="cursor-pointer text-sm font-medium">Atur pemetaan kolom (opsional)</summary>
                    <p class="my-3 text-sm text-muted">Judul kolom dikenali otomatis. Isi nama judul pada file Anda jika berbeda. Kolom tambahan akan diabaikan.</p>
                    <div class="form-grid">
                        @foreach($importColumns as $field => $definition)
                            <x-ui.field :name="'mapping['.$field.']'" :id="'import-mapping-'.$field" :label="$definition['label']" :value="old('mapping.'.$field)" :placeholder="$definition['label']" maxlength="100" />
                        @endforeach
                    </div>
                </details>
                <a href="{{ route('products.import.template') }}" class="ui-button ui-button--secondary justify-self-start"><x-ui.icon name="export" :size="18" />Download Template Excel</a>
                <footer class="dialog-actions"><button type="button" class="ui-button ui-button--cancel" @click="$el.closest('dialog').close()">Batalkan</button><button type="submit" class="ui-button" :disabled="submitting" x-text="submitting ? 'Mengimpor…' : 'Import Produk'">Import Produk</button></footer>
            </form>
        @endif
    </div>
</x-ui.dialog>
