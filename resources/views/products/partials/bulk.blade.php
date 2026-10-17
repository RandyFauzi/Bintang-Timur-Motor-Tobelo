<x-ui.dialog name="product-bulk-edit" title="Edit Massal Produk" description="Perubahan berlaku untuk semua produk terpilih. Kosongkan kolom untuk mempertahankan nilai sebelumnya." :show="old('_dialog') === 'product-bulk-edit'">
    <form method="POST" action="{{ route('products.bulk.update') }}" class="dialog-form" @submit="submitting = true">
        @csrf @method('PATCH')
        <input type="hidden" name="_dialog" value="product-bulk-edit">
        <template x-for="id in selected" :key="id"><input type="hidden" name="selected[]" :value="id"></template>
        <p class="text-sm font-medium" x-text="selected.length + ' produk terpilih'"></p>
        <div class="form-grid">
            @foreach(['purchase_price'=>'Modal', 'selling_price'=>'Harga Jual', 'quantity'=>'Jumlah Stok', 'stock_minimum'=>'Stok Minimum'] as $field=>$label)
                <x-ui.field :name="'changes['.$field.']'" :id="'bulk-'.$field" :label="$label" type="number" min="0" :max="in_array($field, ['quantity', 'stock_minimum']) ? 100000000 : 999999999999" :step="in_array($field, ['quantity', 'stock_minimum']) ? '1' : '0.01'" :value="old('changes.'.$field)" placeholder="Tetap seperti semula" />
            @endforeach
            <x-ui.field name="changes[product_kind]" id="bulk-product-kind" label="Tipe Produk"><select id="bulk-product-kind" name="changes[product_kind]" class="form-input"><option value="">Tetap seperti semula</option>@foreach(\App\Models\Product::KINDS as $value=>$label)<option value="{{ $value }}" @selected(old('changes.product_kind') === $value)>{{ $label }}</option>@endforeach</select></x-ui.field>
            @foreach(['brand_id'=>['Merk',$brands], 'type_id'=>['Jenis Part',$types], 'color_id'=>['Warna',$colors]] as $field=>[$label,$options])
                <x-ui.field :name="'changes['.$field.']'" :id="'bulk-'.$field" :label="$label"><select id="bulk-{{ $field }}" name="changes[{{ $field }}]" class="form-input"><option value="">Tetap seperti semula</option>@foreach($options as $option)<option value="{{ $option->id }}" @selected((string) old('changes.'.$field) === (string) $option->id)>{{ $option->name }}</option>@endforeach</select></x-ui.field>
            @endforeach
        </div>
        <p class="text-xs text-muted">Jumlah Stok akan menjadi nilai yang diisi untuk setiap produk, bukan ditambahkan. Selisih stok tercatat di riwayat.</p>
        <footer class="dialog-actions"><button type="button" class="ui-button ui-button--cancel" @click="$el.closest('dialog').close()">Batalkan</button><button type="submit" class="ui-button" :disabled="submitting || !selected.length">Simpan Perubahan</button></footer>
    </form>
</x-ui.dialog>
<x-ui.dialog name="product-bulk-delete" title="Hapus Produk Terpilih" description="Produk akan dihapus dari katalog. Riwayat transaksi dan retur tetap tersimpan." :show="old('_dialog') === 'product-bulk-delete'">
    <form method="POST" action="{{ route('products.bulk.destroy') }}" class="dialog-form" @submit="submitting = true">
        @csrf @method('DELETE')
        <input type="hidden" name="_dialog" value="product-bulk-delete">
        <template x-for="id in selected" :key="id"><input type="hidden" name="selected[]" :value="id"></template>
        <p class="text-sm">Hapus <strong x-text="selected.length"></strong> produk terpilih?</p>
        <p class="text-xs text-muted">Jika ada produk dengan stok yang sedang dipesan, seluruh penghapusan dibatalkan.</p>
        <footer class="dialog-actions"><button type="button" class="ui-button ui-button--cancel" @click="$el.closest('dialog').close()">Batalkan</button><button type="submit" class="ui-button" :disabled="submitting || !selected.length">Hapus Terpilih</button></footer>
    </form>
</x-ui.dialog>
