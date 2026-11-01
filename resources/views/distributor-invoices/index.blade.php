<x-app-layout>
<div class="catalogue-page procurement-detail-page invoice-page grid gap-5" x-data="invoiceSelection(@js(old('selected', [])))" :class="{ 'has-selection': selected.length > 0 }" @infinite-scroll-loaded="refreshRows()">
    <header class="catalogue-heading !mb-0 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1>Tagihan Distributor</h1>
            <p class="mt-2 text-sm text-muted">Pantau invoice, jatuh tempo, dan pembayaran pesanan distributor.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" class="ui-button ui-button--secondary" @click="$dispatch('open-dialog', { id: 'invoice-import' })">
                <x-ui.icon name="export" :size="18" />Import Excel
            </button>
            <button type="button" class="ui-button" @click="$dispatch('open-dialog', { id: 'invoice-create' })">
                <x-ui.icon name="add" :size="18" />Tambah Invoice
            </button>
            <a class="ui-button ui-button--secondary" href="{{ route('purchase-orders.create') }}" title="Buat PO baru untuk menghasilkan tagihan otomatis">
                <x-ui.icon name="cart" :size="18" />Buat PO
            </a>
        </div>
    </header>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach(['total'=>'Total Faktur (Bruto)','paid'=>'Sudah Dibayarkan','outstanding'=>'Sisa Tagihan','overdue'=>'Perlu Dibayar'] as $key=>$label)<div class="catalogue-surface p-5"><p class="text-sm text-muted">{{ $label }}</p><p @class(['mt-3 text-xl font-semibold','text-danger'=>$key === 'overdue'])>Rp {{ number_format($summary[$key]/100,2,',','.') }}</p></div>@endforeach
    </div>
    <p class="text-xs text-muted">Ringkasan mengikuti pencarian dan distributor. Filter status hanya mengubah daftar di bawah.</p>
    <section class="catalogue-surface" aria-label="Daftar tagihan distributor">
        <form method="GET" class="invoice-filters">
            <label class="form-field flex-1"><span class="form-field__label">Cari tagihan</span><input class="form-input" type="search" name="search" value="{{ request()->string('search') }}" placeholder="Invoice, nomor PO, distributor…"></label>
            <div class="form-field invoice-filter-supplier"><label class="form-field__label" for="invoice-distributor-filter">Distributor</label><x-ui.multiselect name="distributor_id" id="invoice-distributor-filter" label="Semua Distributor" :options="collect([(object) ['id' => '', 'name' => 'Semua Distributor']])->concat($distributors)" :selected="[request('distributor_id', '')]" :multiple="false" :dropdown="true" icon="workshop" /></div>
            <div class="form-field"><label class="form-field__label" for="invoice-payment-filter">Pembayaran</label><x-ui.multiselect name="status" id="invoice-payment-filter" label="Semua Status" :options="collect(\App\Models\DistributorInvoice::STATUSES)->map(fn (string $label, string $value): object => (object) ['id' => $value, 'name' => $label])->values()" :selected="(array) request('status', [])" icon="invoice" /></div>
            <div class="form-field"><label class="form-field__label" for="invoice-timing-filter">Filter Jatuh Tempo</label><x-ui.multiselect name="timing" id="invoice-timing-filter" label="Semua Waktu" :options="collect(['' => 'Semua Waktu', '1' => '1 Bulan', '2' => '2 Bulan', '3' => '3 Bulan', '4' => '4 Bulan'])->map(fn (string $label, string $value): object => (object) ['id' => $value, 'name' => $label])->values()" :selected="[request('timing', '')]" :multiple="false" :dropdown="true" icon="calendar" /></div>
            <button class="ui-button">Cari</button><a class="ui-button ui-button--secondary" href="{{ route('distributor-invoices.index') }}">Reset</a>
        </form>
        <div class="invoice-selection-bar" x-cloak x-show="selected.length" role="group" aria-label="Aksi invoice terpilih">
            <span role="status" aria-live="polite" x-text="selected.length + ' invoice terpilih'"></span>
            <button type="button" class="ui-button ui-button--danger" @click="$dispatch('open-dialog', { id: 'invoice-bulk-delete' })">Hapus Terpilih</button>
            <button type="button" class="ui-button ui-button--secondary" @click="selected = []">Batal Pilih</button>
        </div>
        <p class="px-5 text-sm text-danger" x-show="limitMessage" x-text="limitMessage" role="status" x-cloak></p>
        <div class="flex flex-wrap items-center gap-3 px-5 py-3 text-sm">
            <label class="inline-flex min-h-11 cursor-pointer items-center gap-2"><input type="checkbox" class="rounded" :checked="allSelected" x-effect="$el.indeterminate = selected.length > 0 && !allSelected" @change="toggleAll($event.target.checked)">Pilih Semua</label>
            <span class="text-xs text-muted">Invoice yang sudah dimuat, maksimal 100 sekali hapus.</span>
        </div>
        @include('distributor-invoices.list')
    </section>

    <!-- Dialog: Tambah Tagihan Manual -->
    <x-ui.dialog name="invoice-create" title="Tambah Tagihan Manual" description="Catat faktur tagihan langsung dari distributor tanpa melalui alur PO." :show="old('_dialog') === 'invoice-create'" wide>
        <form method="POST" action="{{ route('distributor-invoices.store') }}" class="dialog-form" x-data="{ receiptShipment: @js(old('shipment_id', '')) }" @change="if ($event.target.name === 'shipment_id') receiptShipment = $event.target.value" @submit="submitting = true">
            @csrf
            <input type="hidden" name="_dialog" value="invoice-create">
            <div class="form-grid">
                <div class="form-field relative" :class="{ 'z-30': open, 'z-10': !open }" x-data="customSelectAutocomplete(@js($distributors->pluck('name')), @js(old('distributor_name', '')))" @click.outside="close()" @keydown.escape="close()">
                    <label for="create_distributor_name" class="form-field__label">Distributor <span class="text-danger">*</span></label>
                    <div class="relative flex items-center">
                        <input
                            id="create_distributor_name"
                            name="distributor_name"
                            type="text"
                            class="form-input !pr-16 w-full"
                            placeholder="Tulis atau cari distributor..."
                            autocomplete="off"
                            role="combobox"
                            x-ref="input"
                            x-model="query"
                            @focus="openDropdown()"
                            @input="openDropdown()"
                            @keydown.arrow-down.prevent="onArrowDown()"
                            @keydown.arrow-up.prevent="onArrowUp()"
                            @keydown.enter="onEnter($event)"
                            @keydown.tab="close()"
                            required
                            maxlength="255"
                        >
                        <div class="absolute right-2 flex items-center gap-0.5">
                            <button
                                type="button"
                                x-show="query.trim()"
                                x-cloak
                                class="flex items-center justify-center w-6 h-6 rounded text-muted hover:text-stone-900 hover:bg-stone-100 transition-colors"
                                @click="clear()"
                                title="Kosongkan distributor"
                                aria-label="Kosongkan distributor"
                            >
                                <x-ui.icon name="close" :size="13" />
                            </button>
                            <button
                                type="button"
                                class="flex items-center justify-center w-7 h-7 rounded text-muted hover:text-stone-900 hover:bg-stone-100 transition-colors"
                                @click="toggle()"
                                title="Buka pilihan distributor"
                                aria-label="Buka pilihan distributor"
                                tabindex="-1"
                            >
                                <x-ui.icon name="chevron" :size="13" class="transition-transform duration-200" x-bind:class="{ '-rotate-90': open, 'rotate-90': !open }" />
                            </button>
                        </div>
                    </div>
                    <div x-show="open" x-cloak x-transition.opacity class="system-autocomplete-menu" role="listbox" aria-label="Pilihan distributor" tabindex="-1">
                        <div x-ref="menuList" class="max-h-52 overflow-y-auto py-1">
                            <template x-for="(item, index) in filtered" :key="item">
                                <button
                                    type="button"
                                    class="system-autocomplete-item"
                                    :class="{ 'is-highlighted': highlightedIndex === index, 'is-selected': query.trim().toLowerCase() === item.toLowerCase() }"
                                    @click="select(item)"
                                    @mouseenter="highlightedIndex = index"
                                    role="option"
                                >
                                    <span class="truncate font-medium" x-text="item"></span>
                                    <span x-show="query.trim().toLowerCase() === item.toLowerCase()" class="text-xs text-green-700 font-semibold" aria-hidden="true">✓</span>
                                </button>
                            </template>
                            <div x-show="filtered.length === 0 && query.trim()" class="px-3 py-3 text-center text-xs text-muted">
                                <p>Tidak ada distributor terdaftar yang cocok.</p>
                                <p class="mt-1 font-medium text-stone-800">"<span x-text="query.trim()"></span>" akan disimpan sebagai distributor baru.</p>
                            </div>
                            <div x-show="options.length === 0 && !query.trim()" class="px-3 py-3 text-center text-xs text-muted">
                                Belum ada daftar distributor. Ketik nama distributor baru di atas.
                            </div>
                        </div>
                        <div x-show="isCustomNewValue && filtered.length > 0" class="px-3 py-2 border-t border-stone-100 bg-stone-50/80 text-xs text-stone-600 flex items-center justify-between">
                            <span>Distributor baru: <strong class="text-stone-900" x-text="query.trim()"></strong></span>
                            <span class="text-[11px] text-muted">Tersimpan otomatis</span>
                        </div>
                    </div>
                    @error('distributor_name')<p class="field-error">{{ $message }}</p>@enderror
                    @error('distributor_id')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <x-ui.field name="reference" label="Nomor Invoice (Opsional)" :value="old('reference')" maxlength="255" placeholder="Otomatis jika dikosongkan" hint="Kosongkan untuk penomoran otomatis sistem (INV-MAN-...)." />
                <x-ui.field name="received_on" label="Tanggal Masuk Barang" type="date" :value="old('received_on')" hint="Opsional. Otomatis mengikuti kargo kapal (jika dipilih), atau isi manual jika ada tanggal masuk spesifik." />
                <x-ui.field name="invoiced_on" label="Tanggal Faktur Distributor" type="date" :value="old('invoiced_on', \App\Models\DistributorInvoice::today()->toDateString())" hint="Tanggal yang tertera pada faktur/nota distributor." />
                <x-ui.field name="due_on" label="Jatuh Tempo" type="date" :value="old('due_on')" required hint="Batas akhir pembayaran faktur." />
                <x-ui.field name="total" label="Total Tagihan (Rp)" type="number" min="0.01" max="90000000000000" step="0.01" :value="old('total')" placeholder="Contoh: 15000000" required />
                <x-ui.field name="supplier_reference" label="Nomor Nota / Surat Jalan (Opsional)" :value="old('supplier_reference')" maxlength="255" placeholder="Contoh: FAK-88912" />
                <x-ui.field name="shipment_id" label="Kargo Kapal (Opsional)">
                    <select id="create_shipment_id" name="shipment_id" class="form-input">
                        <option value="">Belum terhubung ke kargo</option>
                        @foreach($shipments as $shipment)
                            <option value="{{ $shipment->id }}" @selected(old('shipment_id') == $shipment->id)>{{ $shipment->reference }} - {{ $shipment->cargo_name }}{{ $shipment->delivered_at ? ' (Sudah Sampai)' : '' }}</option>
                        @endforeach
                    </select>
                </x-ui.field>
                <div class="form-grid__full">
                    <x-ui.field name="notes" label="Catatan Tagihan (Opsional)">
                        <textarea id="create_notes" name="notes" rows="3" maxlength="2000" class="form-input w-full" placeholder="Catatan tambahan mengenai faktur tagihan ini">{{ old('notes') }}</textarea>
                    </x-ui.field>
                </div>
            </div>
            <footer class="dialog-actions">
                <button type="button" class="ui-button ui-button--cancel" @click="$el.closest('dialog').close()">Batalkan</button>
                <button type="submit" class="ui-button" :disabled="submitting" x-text="submitting ? 'Menyimpan…' : 'Simpan Tagihan'">Simpan Tagihan</button>
            </footer>
        </form>
    </x-ui.dialog>

    <!-- Dialog: Import Tagihan Excel -->
    <x-ui.dialog name="invoice-import" title="Import Tagihan Distributor (Excel)" description="Upload file Excel .xlsx untuk mengimpor banyak tagihan sekaligus ke sistem." :show="old('_dialog') === 'invoice-import'">
        <form method="POST" action="{{ route('distributor-invoices.import') }}" enctype="multipart/form-data" class="dialog-form" x-data="invoiceImportForm" @submit="submit($event)" :aria-busy="submitting">
            @csrf
            <input type="hidden" name="_dialog" value="invoice-import">
            <div class="rounded-xl border p-4">
                <x-ui.field name="file" id="invoice-import-file" label="Pilih File Excel (.xlsx)" hint="Maksimal 5 MB. Jika validasi gagal, pilih ulang file sebelum mengimpor.">
                    <input class="form-input w-full" type="file" id="invoice-import-file" name="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" x-ref="invoiceFile" @change="selectFile()" aria-describedby="invoice-import-file-help invoice-import-file-status" required>
                </x-ui.field>
                <p id="invoice-import-file-status" class="mt-2 break-words text-sm" role="status" aria-live="polite" x-text="fileName ? 'File dipilih: ' + fileName : 'Belum ada file dipilih.'">Belum ada file dipilih.</p>
                <p class="mt-2 field-error" role="alert" x-show="uploadError" x-text="uploadError" x-cloak></p>
            </div>
            <div class="rounded-xl border border-blue-100 bg-blue-50/70 p-4 text-xs text-blue-900 leading-relaxed space-y-2">
                <p class="font-medium text-sm text-blue-950">Panduan Import Tagihan:</p>
                <ul class="list-disc pl-4 space-y-1">
                    <li>Gunakan template Excel resmi agar judul kolom terdeteksi otomatis.</li>
                    <li>Kolom <strong>Distributor</strong>, <strong>Jatuh Tempo</strong>, dan <strong>Total Tagihan</strong> wajib diisi.</li>
                    <li>Jika nama distributor belum ada di database, sistem akan otomatis mendaftarkannya.</li>
                    <li>Nomor invoice bersifat opsional. Jika kosong, sistem membuatkan nomor otomatis (INV-MAN-...).</li>
                    <li>Format tanggal yang didukung: <code>YYYY-MM-DD</code> atau <code>DD/MM/YYYY</code>.</li>
                </ul>
            </div>
            <div class="flex items-center justify-between">
                <a href="{{ route('distributor-invoices.import.template') }}" class="ui-button ui-button--secondary" download>
                    <x-ui.icon name="export" :size="16" />Download Template Excel (.xlsx)
                </a>
            </div>
            <footer class="dialog-actions">
                <button type="button" class="ui-button ui-button--cancel" @click="$el.closest('dialog').close()">Batalkan</button>
                <button type="submit" class="ui-button" :disabled="submitting || !fileName || !!uploadError" x-text="submitting ? 'Mengimpor…' : 'Mulai Import'">Mulai Import</button>
            </footer>
        </form>
    </x-ui.dialog>

    <!-- Dialog: Edit Tagihan Distributor -->
    <x-ui.dialog name="edit-invoice" title="Edit Tagihan Distributor" description="Perbarui rincian, distributor, jatuh tempo, atau status kargo tagihan." wide>
        <form method="POST" class="dialog-form"
            x-data="{
                updateUrl: '',
                version: 1,
                isManual: true,
                distributor_id: '',
                distributor_name: '',
                distributor_options: @js($distributors->pluck('name')),
                distributor_open: false,
                distributor_highlight: -1,
                get filteredDistributors() {
                    const q = (this.distributor_name || '').trim().toLowerCase();
                    if (!q) return this.distributor_options.slice(0, 40);
                    return this.distributor_options.filter(opt => opt.toLowerCase().includes(q)).slice(0, 40);
                },
                get hasExactDistributor() {
                    const q = (this.distributor_name || '').trim().toLowerCase();
                    return this.distributor_options.some(opt => opt.toLowerCase() === q);
                },
                get isNewDistributor() {
                    return (this.distributor_name || '').trim().length > 0 && !this.hasExactDistributor;
                },
                selectDistributor(name) {
                    this.distributor_name = name;
                    this.distributor_open = false;
                    this.distributor_highlight = -1;
                },
                clearDistributor() {
                    this.distributor_name = '';
                    this.distributor_open = true;
                    this.distributor_highlight = -1;
                    this.$nextTick(() => document.getElementById('edit_distributor_name_idx')?.focus());
                },
                toggleDistributor() {
                    this.distributor_open = !this.distributor_open;
                    this.distributor_highlight = -1;
                    if (this.distributor_open) {
                        this.$nextTick(() => document.getElementById('edit_distributor_name_idx')?.focus());
                    }
                },
                reference: '',
                invoiced_on: '',
                received_on: '',
                due_on: '',
                total: '',
                min_total: '0.00',
                supplier_reference: '',
                shipment_id: '',
                notes: '',
                discrepancy_notes: '',
                claim_amount: '0.00',
                note_issued: false
            }"
            :action="updateUrl"
            @open-edit-invoice.window="
                updateUrl = $event.detail.updateUrl;
                version = $event.detail.version;
                isManual = $event.detail.isManual;
                distributor_id = $event.detail.distributor_id || '';
                distributor_name = $event.detail.distributor_name || '';
                reference = $event.detail.reference || '';
                invoiced_on = $event.detail.invoiced_on || '';
                received_on = $event.detail.received_on || '';
                due_on = $event.detail.due_on || '';
                total = $event.detail.total || '';
                min_total = $event.detail.min_total || '0.00';
                supplier_reference = $event.detail.supplier_reference || '';
                shipment_id = $event.detail.shipment_id || '';
                notes = $event.detail.notes || '';
                discrepancy_notes = $event.detail.discrepancy_notes || '';
                claim_amount = $event.detail.claim_amount || '0.00';
                note_issued = $event.detail.note_issued || false;
                $dispatch('open-dialog', { id: 'edit-invoice' });
            "
            @submit="submitting = true"
        >
            @csrf @method('PATCH')
            <input type="hidden" name="_dialog" value="invoice-edit">
            <input type="hidden" name="version" :value="version">

            <div class="form-grid">
                <template x-if="isManual">
                    <div class="form-field relative" :class="{ 'z-30': distributor_open, 'z-10': !distributor_open }" @click.outside="distributor_open = false" @keydown.escape="distributor_open = false">
                        <label for="edit_distributor_name_idx" class="form-field__label">Distributor <span class="text-danger">*</span></label>
                        <div class="relative flex items-center">
                            <input
                                id="edit_distributor_name_idx"
                                name="distributor_name"
                                type="text"
                                class="form-input !pr-16 w-full"
                                placeholder="Tulis atau cari distributor..."
                                autocomplete="off"
                                x-model="distributor_name"
                                @focus="distributor_open = true; distributor_highlight = -1"
                                @input="distributor_open = true; distributor_highlight = -1"
                                @keydown.arrow-down.prevent="
                                    if (!distributor_open) { distributor_open = true; distributor_highlight = 0; }
                                    else if (filteredDistributors.length > 0) { distributor_highlight = (distributor_highlight + 1) % filteredDistributors.length; }
                                "
                                @keydown.arrow-up.prevent="
                                    if (!distributor_open) { distributor_open = true; distributor_highlight = filteredDistributors.length - 1; }
                                    else if (filteredDistributors.length > 0) { distributor_highlight = (distributor_highlight - 1 + filteredDistributors.length) % filteredDistributors.length; }
                                "
                                @keydown.enter="
                                    if (distributor_open && distributor_highlight >= 0 && distributor_highlight < filteredDistributors.length) {
                                        $event.preventDefault();
                                        selectDistributor(filteredDistributors[distributor_highlight]);
                                    }
                                "
                                required
                                maxlength="255"
                            >
                            <div class="absolute right-2 flex items-center gap-0.5">
                                <button
                                    type="button"
                                    x-show="(distributor_name || '').trim()"
                                    x-cloak
                                    class="flex items-center justify-center w-6 h-6 rounded text-muted hover:text-stone-900 hover:bg-stone-100 transition-colors"
                                    @click="clearDistributor()"
                                    title="Kosongkan distributor"
                                    aria-label="Kosongkan distributor"
                                >
                                    <x-ui.icon name="close" :size="13" />
                                </button>
                                <button
                                    type="button"
                                    class="flex items-center justify-center w-7 h-7 rounded text-muted hover:text-stone-900 hover:bg-stone-100 transition-colors"
                                    @click="toggleDistributor()"
                                    title="Buka pilihan distributor"
                                    aria-label="Buka pilihan distributor"
                                    tabindex="-1"
                                >
                                    <x-ui.icon name="chevron" :size="13" class="transition-transform duration-200" x-bind:class="{ '-rotate-90': distributor_open, 'rotate-90': !distributor_open }" />
                                </button>
                            </div>
                        </div>

                        <div x-show="distributor_open" x-cloak x-transition.opacity class="system-autocomplete-menu" role="listbox" aria-label="Pilihan distributor" tabindex="-1">
                            <div class="max-h-52 overflow-y-auto py-1">
                                <template x-for="(item, index) in filteredDistributors" :key="item">
                                    <button
                                        type="button"
                                        class="system-autocomplete-item"
                                        :class="{ 'is-highlighted': distributor_highlight === index, 'is-selected': (distributor_name || '').trim().toLowerCase() === item.toLowerCase() }"
                                        @click="selectDistributor(item)"
                                        @mouseenter="distributor_highlight = index"
                                        role="option"
                                    >
                                        <span class="truncate font-medium" x-text="item"></span>
                                        <span x-show="(distributor_name || '').trim().toLowerCase() === item.toLowerCase()" class="text-xs text-green-700 font-semibold" aria-hidden="true">✓</span>
                                    </button>
                                </template>
                                <div x-show="filteredDistributors.length === 0 && (distributor_name || '').trim()" class="px-3 py-3 text-center text-xs text-muted">
                                    <p>Tidak ada distributor terdaftar yang cocok.</p>
                                    <p class="mt-1 font-medium text-stone-800">"<span x-text="distributor_name.trim()"></span>" akan disimpan sebagai distributor baru.</p>
                                </div>
                                <div x-show="distributor_options.length === 0 && !(distributor_name || '').trim()" class="px-3 py-3 text-center text-xs text-muted">
                                    Belum ada daftar distributor. Ketik nama distributor baru di atas.
                                </div>
                            </div>
                            <div x-show="isNewDistributor && filteredDistributors.length > 0" class="px-3 py-2 border-t border-stone-100 bg-stone-50/80 text-xs text-stone-600 flex items-center justify-between">
                                <span>Distributor baru: <strong class="text-stone-900" x-text="distributor_name.trim()"></strong></span>
                                <span class="text-[11px] text-muted">Tersimpan otomatis</span>
                            </div>
                        </div>
                    </div>
                </template>
                <template x-if="!isManual">
                    <div class="form-field">
                        <label class="form-field__label">Distributor (PO)</label>
                        <div class="form-input bg-gray-50 text-gray-700 font-medium" x-text="distributor_name"></div>
                    </div>
                </template>

                <template x-if="isManual">
                    <x-ui.field name="reference" label="Nomor Invoice" required>
                        <input id="edit_reference_idx" type="text" name="reference" class="form-input" x-model="reference" maxlength="255" required>
                    </x-ui.field>
                </template>
                <template x-if="!isManual">
                    <div class="form-field">
                        <label class="form-field__label">Nomor Invoice (PO)</label>
                        <div class="form-input bg-gray-50 text-gray-700 font-medium" x-text="reference"></div>
                    </div>
                </template>

                <template x-if="isManual">
                    <x-ui.field name="total" label="Total Tagihan (Rp)" required hint="Tidak boleh kurang dari nominal yang sudah dibayar.">
                        <input id="edit_total_idx" type="number" name="total" class="form-input" x-model="total" min="0.01" max="90000000000000" step="0.01" required>
                    </x-ui.field>
                </template>

                <x-ui.field name="received_on" label="Tanggal Masuk Barang" id="edit_received_on_idx" hint="Otomatis mengikuti kedatangan kargo, tetapi bisa diubah/diedit manual jika ada koreksi.">
                    <input id="edit_received_on_idx" type="date" name="received_on" class="form-input" x-model="received_on">
                </x-ui.field>
                <x-ui.field name="invoiced_on" label="Tanggal Faktur Distributor">
                    <input id="edit_invoiced_on_idx" type="date" name="invoiced_on" class="form-input" x-model="invoiced_on" :readonly="note_issued">
                </x-ui.field>

                <x-ui.field name="due_on" label="Jatuh Tempo" required>
                    <input id="edit_due_on_idx" type="date" name="due_on" class="form-input" x-model="due_on" required>
                </x-ui.field>

                <x-ui.field name="supplier_reference" label="Nomor Nota Distributor (Opsional)">
                    <input id="edit_supplier_reference_idx" type="text" name="supplier_reference" class="form-input" x-model="supplier_reference" maxlength="255" :readonly="note_issued">
                </x-ui.field>

                <x-ui.field name="shipment_id" label="Kargo Kapal">
                    <select id="edit_shipment_id_idx" name="shipment_id" class="form-input" x-model="shipment_id" :disabled="note_issued">
                        <option value="">Belum terhubung ke kargo</option>
                        @foreach($shipments as $shipment)
                            <option value="{{ $shipment->id }}">{{ $shipment->reference }} - {{ $shipment->cargo_name }}{{ $shipment->delivered_at ? ' (Sudah Sampai)' : '' }}</option>
                        @endforeach
                    </select>
                </x-ui.field>

                <div class="form-grid__full">
                    <x-ui.field name="discrepancy_notes" label="Keterangan Retur / Barang Kurang / Rusak / Selisih">
                        <textarea id="edit_discrepancy_notes_idx" name="discrepancy_notes" rows="3" maxlength="4000" class="form-input w-full" x-model="discrepancy_notes" placeholder="Tuliskan keterangan jika ada retur atau selisih barang"></textarea>
                    </x-ui.field>
                </div>

                <x-ui.field name="claim_amount" label="Nilai Klaim Dicatat (Rp)">
                    <input id="edit_claim_amount_idx" type="number" name="claim_amount" class="form-input" x-model="claim_amount" min="0" step="0.01">
                </x-ui.field>

                <div class="form-grid__full">
                    <x-ui.field name="notes" label="Catatan Tagihan (Opsional)">
                        <textarea id="edit_notes_idx" name="notes" rows="2" maxlength="2000" class="form-input w-full" x-model="notes" placeholder="Catatan internal"></textarea>
                    </x-ui.field>
                </div>
            </div>

            <footer class="dialog-actions">
                <button type="button" class="ui-button ui-button--cancel" @click="$el.closest('dialog').close()">Batalkan</button>
                <button type="submit" class="ui-button" :disabled="submitting" x-text="submitting ? 'Menyimpan…' : 'Simpan Perubahan'">Simpan Perubahan</button>
            </footer>
        </form>
    </x-ui.dialog>

    <!-- Dialog: Hapus Tagihan -->
    <x-ui.dialog name="delete-invoice" title="Hapus Tagihan Distributor" description="Tagihan akan dihapus permanen beserta catatan pembayaran terkait.">
        <form method="POST" class="dialog-form" x-data="{ invoiceRef: '', distName: '', poRef: '', version: 1, hasPayments: false, deleteUrl: '' }" :action="deleteUrl" @open-delete-invoice.window="invoiceRef = $event.detail.reference; distName = $event.detail.distributor; poRef = $event.detail.po; version = $event.detail.version; hasPayments = $event.detail.hasPayments; deleteUrl = '{{ url('distributor-invoices') }}/' + $event.detail.id; $dispatch('open-dialog', { id: 'delete-invoice' })" @submit="submitting = true">
            @csrf @method('DELETE')
            <input type="hidden" name="version" :value="version">
            <p class="text-sm">Hapus tagihan <strong x-text="invoiceRef"></strong> distributor <strong x-text="distName"></strong> (<span x-text="poRef === 'Manual' ? 'Tagihan Manual' : 'PO: ' + poRef"></span>)?</p>
            <template x-if="hasPayments">
                <p class="rounded-xl bg-red-50 p-3 text-xs text-red-700">Perhatian: Tagihan ini memiliki catatan pembayaran. Semua data pembayaran terkait akan ikut terhapus permanen.</p>
            </template>
            <footer class="dialog-actions">
                <button type="button" class="ui-button ui-button--cancel" @click="$el.closest('dialog').close()">Batalkan</button>
                <button type="submit" class="ui-button ui-button--danger" :disabled="submitting || !deleteUrl" x-text="submitting ? 'Menghapus…' : 'Ya, Hapus Tagihan'">Ya, Hapus Tagihan</button>
            </footer>
        </form>
    </x-ui.dialog>
    <x-ui.dialog name="invoice-bulk-delete" title="Hapus Invoice Terpilih" description="Invoice dan seluruh pembayaran terkait akan dihapus permanen." :show="old('_dialog') === 'invoice-bulk-delete'">
        <form method="POST" action="{{ route('distributor-invoices.bulk.destroy', [], false) }}" class="dialog-form" @submit="submitting = true">
            @csrf @method('DELETE')
            <input type="hidden" name="_dialog" value="invoice-bulk-delete">
            <template x-for="(invoice, index) in selected" :key="invoice.id"><div><input type="hidden" :name="'selected[' + index + '][id]'" :value="invoice.id"><input type="hidden" :name="'selected[' + index + '][version]'" :value="invoice.version"></div></template>
            <p class="text-sm">Hapus <strong x-text="selected.length"></strong> invoice terpilih beserta catatan pembayarannya? PO, pengiriman, dan stok tidak dihapus.</p>
            <p class="text-sm text-muted">Maksimal 100 invoice sekali hapus. Jika ada invoice yang berubah, seluruh penghapusan dibatalkan. Muat ulang dan periksa kembali pilihan Anda.</p>
            <footer class="dialog-actions"><button type="button" class="ui-button ui-button--cancel" @click="$el.closest('dialog').close()">Batalkan</button><button type="submit" class="ui-button ui-button--danger" :disabled="submitting || !selected.length" x-text="submitting ? 'Menghapus…' : 'Ya, Hapus Terpilih'">Ya, Hapus Terpilih</button></footer>
        </form>
    </x-ui.dialog>
</div>
</x-app-layout>
