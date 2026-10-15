<x-ui.dialog name="product-form" :title="$product ? 'Edit '.$product->name : 'Tambah Produk'" :description="$product ? 'Edit jumlah stok atau detail informasi produk.' : 'Tambahkan produk baru beserta stok awalnya.'" :show="$product !== null || request()->boolean('create') || old('_dialog') === 'product-form'">
    <form method="POST" action="{{ $product ? route('products.update',$product) : route('products.store') }}" class="dialog-form" @submit="submitting = true">
        @csrf @if($product) @method('PATCH') @endif
        <input type="hidden" name="_dialog" value="product-form">
        <div class="form-grid">
            <x-ui.field name="quantity" label="Jumlah Stok" type="number" :value="$product?->stock?->quantity ?? 0" min="0" max="100000000" required />
            <x-ui.field name="stock_minimum" label="Stok Minimum" type="number" :value="$product?->stock_minimum ?? 0" min="0" max="100000000" required />
        </div>
        <details class="product-form-details" @if(!$product) open @endif>
            <summary>{{ $product ? 'Edit Detail' : 'Informasi Produk' }}</summary>
            <div class="form-grid mt-5">
                <x-ui.field name="name" label="Nama Produk" :value="$product?->name" required maxlength="255" />
                <x-ui.field name="color_name" label="Warna" :value="$product?->color?->name" list="color-options" placeholder="Tulis atau cari warna" maxlength="255" />
                <x-ui.field name="purchase_price" label="Modal" type="number" :value="$product?->purchase_price ?? 0" min="0" step="0.01" required />
                <x-ui.field name="selling_price" label="Harga Jual" type="number" :value="$product?->selling_price ?? 0" min="0" step="0.01" required />
                <x-ui.field name="product_kind" label="Tipe Produk"><select id="product_kind" name="product_kind" class="form-input"><option value="daily" @selected(old('product_kind',$product?->product_kind ?? 'daily') === 'daily')>Harian</option><option value="non_daily" @selected(old('product_kind',$product?->product_kind) === 'non_daily')>Non-Harian</option></select></x-ui.field>
                <x-ui.field name="brand_name" label="Merk" :value="$product?->brand?->name" list="brand-options" placeholder="Tulis atau cari merk" maxlength="255" />
                <x-ui.field name="type_name" label="Jenis Part" :value="$product?->type?->name" list="type-options" placeholder="Tulis atau cari jenis part" maxlength="255" />
                <x-ui.field name="distributor_ids" label="Distributor">
                    <x-ui.multiselect name="distributor_ids" id="distributor_ids" label="Pilih Distributor" :options="$distributors" :selected="old('distributor_ids',$product?->distributors->modelKeys() ?? [])" :submit-on-apply="false" icon="workshop" />
                    <input type="hidden" name="_sync_distributors" value="1"><p class="mt-1 text-xs text-muted">Bisa memilih lebih dari satu distributor.</p>
                </x-ui.field>
                <x-ui.field name="barcode" label="Barcode (opsional)" :value="$product?->barcode" maxlength="100" />
                @if($product)<div class="form-field"><span class="form-field__label">SKU</span><p class="py-2 text-sm text-muted">{{ $product->sku }}</p></div>@else<x-ui.field name="sku" label="SKU (opsional)" hint="Dibuat otomatis jika dikosongkan." maxlength="100" />@endif
            </div>
        </details>
        @foreach(['brand'=>'brands','type'=>'types','color'=>'colors'] as $name=>$collection)<datalist id="{{ $name }}-options">@foreach($$collection as $option)<option value="{{ $option->name }}">@endforeach</datalist>@endforeach
        <footer class="dialog-actions"><button type="button" class="ui-button ui-button--cancel" @click="$el.closest('dialog').close()">Batalkan</button><button type="submit" class="ui-button" :disabled="submitting" x-text="submitting ? 'Menyimpan…' : 'Simpan'">Simpan</button></footer>
    </form>
</x-ui.dialog>
