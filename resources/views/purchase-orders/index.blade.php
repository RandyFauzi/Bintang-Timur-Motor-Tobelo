<x-app-layout>
    <div class="catalogue-page procurement-page grid gap-5" x-data="procurementSelection(@js(old('selected', [])))" :class="{ 'has-selection': selected.length > 0 }" @infinite-scroll-loaded="refreshRows()" @delete-purchase-order.window="deletion = $event.detail; $dispatch('open-dialog', { id: 'delete-purchase-order' })">
        <header class="catalogue-heading !mb-0"><div><h1>Pengadaan Barang</h1><p class="mt-2 text-sm text-muted">Buat perencanaan dan proses pengadaan barang.</p></div></header>
        <nav class="flex flex-wrap gap-2" aria-label="Daftar pengadaan"><a class="ui-button ui-button--secondary" href="{{ route('purchase-orders.index', request()->except(['archived', 'page'])) }}" @if(!request()->boolean('archived')) aria-current="page" @endif>Aktif</a><a class="ui-button ui-button--secondary" href="{{ route('purchase-orders.index', array_replace(request()->except('page'), ['archived' => 1])) }}" @if(request()->boolean('archived')) aria-current="page" @endif>Arsip</a></nav>
        <section class="catalogue-surface min-w-0" aria-label="Daftar pengadaan">
            <form method="GET" action="{{ route('purchase-orders.index') }}">
                <div class="catalogue-toolbar flex-wrap">
                    <label class="catalogue-search"><span class="sr-only">Cari pengadaan atau distributor</span><x-ui.icon name="search" :size="18" /><input type="search" name="search" value="{{ request('search') }}" placeholder="Cari PO, nama rencana, atau distributor..." maxlength="255"></label>
                    <div class="catalogue-toolbar__actions flex-wrap"><button type="submit" class="ui-button ui-button--secondary">Cari</button><a class="ui-button procurement-create" href="{{ route('purchase-orders.create') }}"><x-ui.icon name="add" :size="18" />Buat Pengadaan</a></div>
                </div>
                @if(request()->boolean('archived'))<input type="hidden" name="archived" value="1">@endif
                <div class="catalogue-filters catalogue-filters--scroll procurement-filters" role="region" aria-label="Filter pengadaan, geser untuk melihat pilihan lainnya" tabindex="0">
                    <x-ui.inline-date-filter id="procurement-date-filter" start-name="from" end-name="until" />
                    <x-ui.multiselect name="status" id="procurement-status" label="Semua Status" :options="collect(['draft' => 'Draft', 'pending' => 'In Progress', 'checking' => 'Sedang diperiksa', 'discrepancy' => 'Ada selisih', 'matched' => 'Selesai'])->map(fn (string $label, string $value): object => (object) ['id' => $value, 'name' => $label])->values()" :selected="(array) request('status', [])" icon="status-filter" />
                    <x-ui.multiselect name="distributor_id" id="procurement-distributor" label="Semua Distributor" :options="$distributors" :selected="(array) request('distributor_id', [])" icon="workshop" />
                    @if(request()->filled('per_page'))<input type="hidden" name="per_page" value="{{ request('per_page') }}">@endif
                    @if(request()->filled('search') || request()->filled('status') || request()->filled('distributor_id') || request()->filled('from') || request()->filled('until'))<a class="filter-reset" href="{{ route('purchase-orders.index', request()->boolean('archived') ? ['archived' => 1] : []) }}">Reset Filter</a>@endif
                </div>
            </form>
            @if(!request()->boolean('archived'))
                <div class="flex flex-wrap items-center gap-3 px-4 py-3 text-sm"><label class="inline-flex min-h-11 items-center gap-2"><input type="checkbox" class="rounded" :checked="allSelected" x-effect="$el.indeterminate = selected.length > 0 && !allSelected" @change="toggleAll($event.target.checked)">Pilih Semua</label><span class="text-xs text-muted">Pengadaan yang sudah dimuat, maksimal 100 sekali proses.</span></div>
                <div class="procurement-selection-bar" x-cloak x-show="selected.length" role="group" aria-label="Aksi pengadaan terpilih"><span role="status" aria-live="polite" x-text="selected.length + ' pengadaan terpilih'"></span><button type="button" class="ui-button ui-button--danger" @click="$dispatch('open-dialog', {id:'procurement-bulk-delete'})">Hapus Terpilih</button><button type="button" class="ui-button ui-button--secondary" @click="selected = []">Batal Pilih</button></div>
                <p class="px-4 text-sm text-danger" role="status" x-cloak x-show="limitMessage" x-text="limitMessage"></p>
            @endif
            @include('purchase-orders.list')
        </section>
        <x-ui.dialog name="delete-purchase-draft" title="Hapus Draft Rencana Pengadaan?" description="Draft yang dihapus tidak dapat dikembalikan.">
            <form method="POST" :action="draftDeletion.url" class="dialog-form" @submit="submitting = true">
                @csrf @method('DELETE')
                <input type="hidden" name="version" :value="draftDeletion.version">
                <p class="text-sm">Hapus draft <strong class="break-words" x-text="draftDeletion.name"></strong>?</p>
                <footer class="dialog-actions"><button type="button" class="ui-button ui-button--cancel" @click="$el.closest('dialog').close()">Batalkan</button><button type="submit" class="ui-button" :disabled="submitting || !draftDeletion.url" x-text="submitting ? 'Menghapus…' : 'Ya, Hapus Draft'">Ya, Hapus Draft</button></footer>
            </form>
        </x-ui.dialog>
        <x-ui.dialog name="delete-purchase-order" title="Hapus Pengadaan dari Daftar Aktif?" description="Sistem memeriksa seluruh rencana dan transaksi terkait sebelum menentukan penghapusan atau pengarsipan.">
            <form method="POST" :action="deletion.url" class="dialog-form" @submit="submitting = true">
                @csrf @method('DELETE')
                <input type="hidden" name="version" :value="deletion.version">
                <p class="text-sm">Hapus pengadaan <strong x-text="deletion.reference"></strong>?</p>
                <div class="grid gap-3 text-sm text-muted"><p>Untuk rencana induk, tindakan berlaku pada seluruh order distributor dalam rencana tersebut.</p><p><strong class="text-gray-900">Belum diproses:</strong> rencana, rincian, dan tagihan kosong dihapus permanen.</p><p><strong class="text-gray-900">Sudah diproses:</strong> dipindahkan ke Arsip. Nota, pembayaran, klaim, dan pengiriman tetap tersimpan serta dapat dilanjutkan. Pengadaan dapat dikembalikan ke Aktif.</p></div>
                <footer class="dialog-actions"><button type="button" class="ui-button ui-button--cancel" @click="$el.closest('dialog').close()">Batalkan</button><button type="submit" class="ui-button" :disabled="submitting" x-text="submitting ? 'Memproses…' : 'Ya, Lanjutkan'">Ya, Lanjutkan</button></footer>
            </form>
        </x-ui.dialog>
        <x-ui.dialog name="procurement-bulk-delete" title="Proses Pengadaan Terpilih" description="Pengadaan yang belum diproses dihapus, sedangkan yang sudah diproses diarsipkan." :show="old('_dialog') === 'procurement-bulk-delete'">
            <form method="POST" action="{{ route('purchase-orders.bulk.destroy') }}" class="dialog-form" @submit="submitting = true">
                @csrf @method('DELETE')<input type="hidden" name="_dialog" value="procurement-bulk-delete">
                <template x-for="(entry, index) in selected" :key="entry.id"><div><input type="hidden" :name="'selected['+index+'][id]'" :value="entry.recordId"><input type="hidden" :name="'selected['+index+'][type]'" :value="entry.type"><input type="hidden" :name="'selected['+index+'][version]'" :value="entry.version"><input type="hidden" :name="'selected['+index+'][snapshot]'" :value="entry.snapshot"></div></template>
                <p class="text-sm">Proses <strong x-text="selected.length"></strong> pengadaan terpilih?</p><p class="text-sm text-muted">Draft dan pengadaan yang belum diproses dihapus permanen. Pengadaan yang sudah diproses dipindahkan ke Arsip; nota, pembayaran, klaim, pengiriman, dan stok tetap tersimpan. Jika satu data berubah, seluruh proses dibatalkan. Maksimal 100 pengadaan sekali proses.</p>
                <footer class="dialog-actions"><button type="button" class="ui-button ui-button--cancel" @click="$el.closest('dialog').close()">Batalkan</button><button type="submit" class="ui-button ui-button--danger" :disabled="submitting || !selected.length">Ya, Proses Terpilih</button></footer>
            </form>
        </x-ui.dialog>
    </div>
</x-app-layout>
