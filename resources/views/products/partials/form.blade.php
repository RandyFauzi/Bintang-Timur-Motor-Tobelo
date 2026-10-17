@php($detailsExpanded = ! $product || (old('_dialog') === 'product-form' && old('_edit_details') === '1'))
<x-ui.dialog name="product-form" :title="$product ? 'Edit '.$product->name : 'Tambah Produk'" :description="$product ? 'Edit jumlah stok atau detail informasi produk.' : 'Tambahkan produk baru beserta stok awalnya.'" :show="$product !== null || request()->boolean('create') || old('_dialog') === 'product-form'">
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
            <x-ui.field name="purchase_price" label="Modal" type="number" :value="$product?->purchase_price ?? 0" min="0" step="0.01" required />
            <x-ui.field name="selling_price" label="Harga Jual" type="number" :value="$product?->selling_price ?? 0" min="0" step="0.01" required />
            <x-ui.field name="product_kind" label="Tipe Produk"><select id="product_kind" name="product_kind" class="form-input">@foreach(\App\Models\Product::KINDS as $value => $kindLabel)<option value="{{ $value }}" @selected(old('product_kind',$product?->product_kind ?? 'daily') === $value)>{{ $kindLabel }}</option>@endforeach</select></x-ui.field>
            <x-ui.field name="distributor_ids" label="Distributor">
                <x-ui.multiselect name="distributor_ids" id="distributor_ids" label="Pilih Distributor" :options="$distributors" :selected="old('distributor_ids',$product?->distributors->modelKeys() ?? [])" :submit-on-apply="false" icon="workshop" />
                <input type="hidden" name="_sync_distributors" value="1"><p class="mt-1 text-xs text-muted">Bisa memilih lebih dari satu distributor.</p>
            </x-ui.field>
            <x-ui.field name="barcode" label="Barcode (opsional)" :value="$product?->barcode" maxlength="100" />
            @if($product)<div class="form-field"><span class="form-field__label">SKU</span><p class="py-2 text-sm text-muted">{{ $product->sku }}</p></div>@else<x-ui.field name="sku" label="SKU (opsional)" hint="Dibuat otomatis jika dikosongkan." maxlength="100" />@endif
        </fieldset>
        <div class="form-grid">
            <x-ui.field name="quantity" label="Jumlah Stok" type="number" :value="$product?->stock?->quantity ?? 0" min="0" max="100000000" required />
            <x-ui.field name="stock_minimum" label="Stok Minimum" type="number" :value="$product?->stock_minimum ?? 0" min="0" max="100000000" required />
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
                <p class="text-sm text-muted">Tambahkan banyak produk sekaligus melalui template Excel. Maksimal 500 produk per file (5 MB).</p>
                <x-ui.field name="file" label="File Excel (.xlsx)" type="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required hint="Pilih ulang file jika validasi gagal." />
                <ul class="list-disc pl-5 text-sm text-muted space-y-2">
                    <li>SKU wajib unik. Import hanya menambah produk baru.</li>
                    <li>Isi angka tanpa Rp atau pemisah ribuan; desimal memakai titik. Jumlah Stok dan Stok Minimum wajib diisi.</li>
                    <li>Tipe Produk: Harian, Non-Harian, atau Variasi. Distributor harus sudah terdaftar; pisahkan beberapa nama dengan tanda |.</li>
                    <li>Format kolom SKU dan Barcode sebagai Teks agar angka nol di depan tidak hilang.</li>
                    <li>Semua baris diperiksa terlebih dahulu. Jika ada kesalahan, seluruh import dibatalkan.</li>
                </ul>
                <a href="{{ route('products.import.template') }}" class="ui-button ui-button--secondary justify-self-start"><x-ui.icon name="export" :size="18" />Download Template Excel</a>
                <footer class="dialog-actions"><button type="button" class="ui-button ui-button--cancel" @click="$el.closest('dialog').close()">Batalkan</button><button type="submit" class="ui-button" :disabled="submitting" x-text="submitting ? 'Mengimpor…' : 'Import Produk'">Import Produk</button></footer>
            </form>
        @endif
    </div>
</x-ui.dialog>
