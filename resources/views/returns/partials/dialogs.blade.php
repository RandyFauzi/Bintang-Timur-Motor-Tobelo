@php
    $mode = old('_dialog') ? str_replace('return-', '', old('_dialog')) : request('mode', 'edit');
    $mode = in_array($mode, ['edit','status','cancel','delete','activity'], true) ? $mode : 'edit';
    $initialProduct = $selectedProduct ? ['id'=>$selectedProduct->id, 'name'=>$selectedProduct->name, 'sku'=>$selectedProduct->sku, 'available'=>max(0, ($selectedProduct->stock?->quantity ?? 0) - ($selectedProduct->stock?->reserved_quantity ?? 0))] : null;
@endphp
<x-ui.dialog class="return-dialog" name="return-create" title="Tambah Retur" description="Cari dan tambahkan barang yang ingin diretur dari stok." :show="request()->boolean('create') || old('_dialog') === 'return-create'">
    <form method="POST" action="{{ route('returns.store') }}" class="dialog-form" @submit="submitting = true">
        @csrf<input type="hidden" name="_dialog" value="return-create"><input type="hidden" name="request_id" value="{{ $requestId }}">
        <x-ui.product-picker :endpoint="route('returns.products')" :initial="$initialProduct" />
        <x-ui.field name="quantity" id="create-quantity" label="Jumlah Produk Diretur" type="number" min="1" max="100000000" step="1" required :value="1" hint="Stok tersedia akan langsung dikurangi sesuai jumlah retur." />
        <x-ui.field name="note_status" label="Status Nota Produk"><select id="note_status" name="note_status" class="form-input" required><option value="pending" @selected(old('note_status','pending') === 'pending')>Belum Dikurangi</option><option value="credited" @selected(old('note_status') === 'credited')>Sudah Dikurangi</option></select></x-ui.field>
        <p class="text-xs text-muted">Status nota menunjukkan pencatatan pengurangan pada nota distributor, terpisah dari stok fisik.</p>
        <footer class="dialog-actions"><button type="button" class="ui-button ui-button--cancel" @click="$el.closest('dialog').close()">Batalkan</button><button type="submit" class="ui-button" :disabled="submitting" x-text="submitting ? 'Menyimpan…' : 'Simpan'">Simpan</button></footer>
    </form>
</x-ui.dialog>
<x-ui.dialog class="return-dialog" style="--return-dialog-width:402px" name="return-bulk" title="Edit Massal" description="Ubah status nota atau jumlah produk untuk semua retur terpilih." :show="old('_dialog') === 'return-bulk'">
    <form method="POST" action="{{ route('returns.bulk') }}" class="dialog-form" @submit="submitting = true">
        @csrf @method('PATCH')<input type="hidden" name="_dialog" value="return-bulk">
        <template x-for="(row,index) in selected" :key="row.id"><div><input type="hidden" :name="'selected['+index+'][id]'" :value="row.id"><input type="hidden" :name="'selected['+index+'][version]'" :value="row.version"></div></template>
        <p class="text-sm" x-text="selected.length + ' retur terpilih'"></p>
        <x-ui.field name="note_status" id="bulk-note-status" label="Status Nota Produk"><select id="bulk-note-status" name="note_status" class="form-input"><option value="">Tetap seperti semula</option><option value="pending" @selected(old('note_status') === 'pending')>Belum Dikurangi</option><option value="credited" @selected(old('note_status') === 'credited')>Sudah Dikurangi</option></select></x-ui.field>
        <x-ui.field name="quantity" id="bulk-quantity" label="Jumlah Produk" type="number" min="1" max="100000000" step="1" placeholder="Tetap seperti semula" hint="Jika diisi, jumlah ini berlaku untuk setiap baris terpilih. Perubahan stok dihitung otomatis." />
        <footer class="dialog-actions"><button type="button" class="ui-button ui-button--cancel" @click="$el.closest('dialog').close()">Batalkan</button><button type="submit" class="ui-button" :disabled="submitting || !selected.length" x-text="submitting ? 'Menyimpan…' : 'Simpan'">Simpan</button></footer>
    </form>
</x-ui.dialog>
@if($editing)
    @if($mode === 'activity')
        <x-ui.dialog name="return-activity" title="Log Aktivitas Retur" :description="$editing->product_snapshot['name']" :show="true" wide>
            <div class="dialog-form">@foreach($editing->events as $event)<article class="rounded-xl border border-gray-200 p-4"><p class="font-medium">{{ ['created'=>'Retur Ditambahkan','updated'=>'Retur Diedit','cancelled'=>'Retur Dibatalkan','deleted'=>'Data Retur Dihapus'][$event->action] ?? $event->action }}</p><p class="mt-2 text-sm text-muted">{{ $event->actor_name }} · {{ $event->created_at->format('d/m/Y H:i:s') }}</p><p class="mt-2 text-sm">Jumlah: {{ $event->before_state['quantity'] ?? 0 }} → {{ $event->after_state['quantity'] }} · Perubahan stok: {{ $event->stock_delta > 0 ? '+' : '' }}{{ $event->stock_delta }}</p><p class="mt-2 text-sm">Nota: {{ ($event->before_state['note_status'] ?? null) === 'credited' ? 'Sudah Dikurangi' : 'Belum Dikurangi' }} → {{ $event->after_state['note_status'] === 'credited' ? 'Sudah Dikurangi' : 'Belum Dikurangi' }}</p></article>@endforeach</div>
        </x-ui.dialog>
    @elseif(!$editing->trashed())
        @php
            $titles = ['edit'=>'Edit Jumlah Retur','status'=>'Apakah kamu yakin akan mengubah status?', 'cancel'=>'Apakah Kamu Yakin Ingin Membatalkan Retur?', 'delete'=>'Apakah Kamu Yakin Ingin Menghapus Data Retur?'];
            $descriptions = ['edit'=>'Ubah jumlah barang yang diretur. Stok akan disesuaikan dari selisih jumlah.', 'status'=>'Perubahan akan tercatat di log aktivitas dan tidak mengubah stok.', 'cancel'=>'Barang yang dibatalkan retur akan kembali ke stok & produk.', 'delete'=>'Sisa jumlah retur akan dikembalikan ke stok. Data disembunyikan dan riwayat tetap tersimpan di log aktivitas.'];
            $action = match($mode) {'cancel'=>route('returns.cancel',$editing), 'delete'=>route('returns.destroy',$editing), default=>route('returns.update',$editing)};
        @endphp
        <x-ui.dialog class="return-dialog" :name="'return-'.$mode" :title="$titles[$mode] ?? $titles['edit']" :description="$descriptions[$mode] ?? $descriptions['edit']" :show="true">
            <form method="POST" action="{{ $action }}" class="dialog-form" @submit="submitting = true">
                @csrf @method($mode === 'delete' ? 'DELETE' : 'PATCH')
                <input type="hidden" name="_dialog" value="return-{{ $mode }}"><input type="hidden" name="_return_id" value="{{ $editing->id }}"><input type="hidden" name="version" value="{{ old('version', $editing->version) }}">
                <p class="text-sm font-medium">{{ $editing->product_snapshot['name'] }}</p>
                @if(in_array($mode, ['edit','cancel']))
                    <x-ui.field name="quantity" id="edit-quantity" :label="$mode === 'cancel' ? 'Jumlah Produk Retur yang Dibatalkan' : 'Jumlah Produk'" type="number" :value="$editing->quantity" min="1" :max="$mode === 'cancel' ? $editing->quantity : 100000000" step="1" required />
                @elseif($mode === 'status')
                    <div class="flex flex-wrap items-center justify-between gap-3"><x-ui.return-note-status :value="$editing->note_status" /><x-ui.icon name="arrow-right" /><x-ui.return-note-status :value="$editing->note_status === 'credited' ? 'pending' : 'credited'" /></div>
                    <input type="hidden" name="note_status" value="{{ $editing->note_status === 'credited' ? 'pending' : 'credited' }}">
                @endif
                <footer class="dialog-actions"><button type="button" class="ui-button ui-button--cancel" @click="$el.closest('dialog').close()">Batalkan</button><button type="submit" class="ui-button" :disabled="submitting">{{ $mode === 'cancel' ? 'Batalkan Retur' : ($mode === 'delete' ? 'Hapus Data Retur' : ($mode === 'status' ? 'Ubah Status' : 'Simpan')) }}</button></footer>
            </form>
        </x-ui.dialog>
    @endif
@endif
