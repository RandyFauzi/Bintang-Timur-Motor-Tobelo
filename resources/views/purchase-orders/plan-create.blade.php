<x-app-layout>
    <div class="catalogue-page plan-page" x-data="purchasePlanForm(@js($initial), @js($distributors), @js(['save' => $saveUrl, 'products' => route('purchase-plans.products'), 'import' => route('purchase-plans.import')]))">
        <header class="plan-heading">
            <a class="plan-back" href="{{ route('purchase-orders.index') }}"><span aria-hidden="true">←</span> Kembali ke daftar Pre Order</a>
            <h1>{{ $plan ? 'Edit Rencana Pengadaan' : 'Buat Rencana Pengadaan' }}</h1>
            <p>Simpan sebagai draft kapan saja, lalu finalisasi saat seluruh kebutuhan sudah dialokasikan.</p>
        </header>
        <nav class="plan-steps" aria-label="Tahap pembuatan rencana">
            @foreach(['Kebutuhan Produk', 'Alokasi Distributor', 'Review & Finalisasi'] as $index => $label)
                @if($index > 0)<span class="plan-steps__line" aria-hidden="true"></span>@endif
                <button type="button" class="plan-step" :class="{ 'is-active': step === {{ $index + 1 }} }" :aria-current="step === {{ $index + 1 }} ? 'step' : null" :disabled="{{ $index + 1 }} > step || saving" @click="goTo({{ $index + 1 }})"><span>{{ $index + 1 }}</span>{{ $label }}</button>
            @endforeach
        </nav>
        <div class="validation-alert" role="alert" x-show="errors.length" x-cloak><p class="font-medium">Periksa kembali rencana pengadaan.</p><template x-for="(error, index) in errors" :key="index"><p class="mt-1 text-sm" x-text="error"></p></template></div>
        <div x-show="step === 1" class="plan-stage">
            <section class="plan-panel">
                <header><h2>Informasi rencana</h2><p>Tentukan identitas rencana sebelum memasukkan kebutuhan produk.</p></header>
                <div class="plan-info-grid">
                    <label class="form-field"><span class="form-field__label">Nama rencana <span class="text-danger">*</span></span><input class="form-input" x-model="name" maxlength="255" placeholder="Contoh: Perencanaan Pengadaan Januari 2027" required></label>
                    <label class="form-field"><span class="form-field__label">Tanggal rencana <span class="text-danger">*</span></span><input class="form-input" type="date" x-model="plannedOn" required></label>
                    <label class="form-field"><span class="form-field__label">ID Rencana (Generated)</span><input class="form-input plan-generated" :value="displayReference" readonly></label>
                </div>
            </section>
            <section class="plan-panel">
                <header><h2>Kebutuhan produk</h2><p>Katalog hanya mengisi atribut ke form ini; data master katalog tidak akan diubah.</p></header>
                <div x-show="!rows.length" class="plan-empty">
                    <img src="{{ Vite::asset('resources/images/icons/plan-empty.svg') }}" width="150" height="150" alt="" aria-hidden="true">
                    <div><h3>Belum ada produk yang ditambahkan</h3><p>Tambah produk katalog yang sudah ada atau masukkan nama produk secara manual.</p></div>
                    <div class="plan-add-actions"><button type="button" class="plan-dashed" @click="add()"><x-ui.icon name="add" :size="16" />Tambah Produk</button><button type="button" class="plan-dashed" @click="$dispatch('open-dialog', {id:'plan-import'})"><x-ui.icon name="export" :size="16" class="rotate-180" />Import .xlsx</button></div>
                </div>
                <div x-show="rows.length" x-cloak class="plan-table-shell">
                    <div class="plan-table-scroll" :class="{ 'is-searching': rows.some(row => row.pickerOpen) }">
                        <table class="plan-table"><thead><tr>@foreach(['Nama Produk & Warna', 'Tipe Produk', 'Merk', 'Jenis Part', 'Stok Saat Ini', 'Kebutuhan', 'Aksi'] as $heading)<th scope="col">{{ $heading }}</th>@endforeach</tr></thead>
                            <tbody><template x-for="(row, index) in rows" :key="row.key"><tr>
                                <td class="plan-product-cell">
                                    <div x-show="row.pickerOpen" class="plan-picker" @click.outside="row.results = []" @keydown.escape.stop="row.results = []">
                                        <label class="plan-search"><x-ui.icon name="search" :size="16" /><input type="search" x-model="row.product_name" @input.debounce.300ms="search(row)" @focus="search(row)" @keydown.arrow-down.prevent="moveResult(row, 1)" @keydown.arrow-up.prevent="moveResult(row, -1)" @keydown.enter.prevent="chooseResult(row)" :aria-label="'Cari produk baris ' + (index + 1)" role="combobox" :aria-expanded="row.results.length > 0" :aria-controls="'plan-results-' + row.key" :aria-activedescendant="row.focusedResult >= 0 ? 'plan-result-' + row.key + '-' + row.focusedResult : null" maxlength="255" placeholder="Cari atau tulis produk baru..."></label>
                                        <button type="button" class="plan-new-link" @click="openManual(index)"><x-ui.icon name="add" :size="16" />Tambah Produk Baru</button>
                                        <div class="plan-results" x-show="row.results.length || row.searching" :id="'plan-results-' + row.key" role="listbox">
                                            <p x-show="row.searching" class="p-3 text-xs text-muted" role="status">Mencari produk…</p>
                                            <template x-for="(product, resultIndex) in row.results" :key="product.product_id"><button type="button" role="option" :aria-selected="row.focusedResult === resultIndex" :id="'plan-result-' + row.key + '-' + resultIndex" :class="{'is-focused': row.focusedResult === resultIndex}" @click="select(row, product)"><span><strong x-text="product.product_name"></strong><small x-text="[product.brand, product.sku].filter(Boolean).join(' · ')"></small></span><span x-text="product.color"></span></button></template>
                                        </div>
                                    </div>
                                    <div x-show="!row.pickerOpen"><span x-text="row.product_name"></span><span class="plan-new-badge" x-show="!row.product_id">Baru</span><small class="mt-2 block text-muted" x-text="row.color || '-'"></small></div>
                                </td>
                                <td class="text-muted" x-text="row.pickerOpen ? '-' : kinds[row.product_kind] || '-'"></td>
                                <td><span class="plan-brand" x-text="row.brand || '-'"></span></td>
                                <td class="text-muted" x-text="row.part_type || '-'"></td>
                                <td class="text-muted" x-text="row.current_stock"></td>
                                <td><input type="number" class="form-input plan-quantity" min="0" max="1000000" step="1" x-model="row.quantity" :aria-label="'Kebutuhan ' + (row.product_name || 'baris ' + (index + 1))"></td>
                                <td><div class="plan-row-actions"><button type="button" @click="openManual(index)" :aria-label="'Edit ' + (row.product_name || 'produk')"><x-ui.icon name="edit" :size="20" /></button><button type="button" @click="remove(index)" :aria-label="'Hapus ' + (row.product_name || 'produk')"><x-ui.icon name="trash" :size="18" /></button></div></td>
                            </tr></template></tbody>
                        </table>
                    </div>
                    <div class="plan-add-actions plan-add-actions--table"><button type="button" class="plan-dashed" @click="add()"><x-ui.icon name="add" :size="16" />Tambah Produk</button><button type="button" class="plan-dashed" @click="$dispatch('open-dialog', {id:'plan-import'})"><x-ui.icon name="export" :size="16" class="rotate-180" />Import .xlsx</button></div>
                </div>
            </section>
        </div>
        <div x-show="step === 2" x-cloak class="plan-stage">
            <template x-for="(row, index) in rows" :key="row.key"><section class="plan-panel">
                <header class="plan-allocation-heading"><button type="button" class="plan-collapse" @click="toggleAllocation(row)" :aria-expanded="openAllocationKey === row.key" :aria-controls="'plan-allocation-' + row.key" :aria-label="(openAllocationKey === row.key ? 'Tutup alokasi ' : 'Tampilkan alokasi ') + row.product_name"><x-ui.icon name="chevron" :size="18" class="plan-collapse-icon" x-bind:class="{'rotate-90': openAllocationKey === row.key}" /></button><div><h2 x-text="row.product_name"></h2><p x-text="[row.color, kinds[row.product_kind], row.brand, row.part_type].filter(Boolean).join(' | ')"></p></div><div class="plan-progress"><div><strong x-text="allocated(row) + '/' + row.quantity + ' Unit'"></strong><span x-text="allocationNote(row)"></span></div><progress :value="allocated(row)" :max="Number(row.quantity) || 1" :aria-label="'Alokasi ' + row.product_name"></progress></div></header>
                <div class="plan-table-shell" :id="'plan-allocation-' + row.key" x-show="openAllocationKey === row.key">
                    <div class="plan-table-scroll"><table class="plan-table plan-allocation-table"><thead><tr><th scope="col">Distributor</th><th scope="col">Kebutuhan</th><th scope="col">Aksi</th></tr></thead><tbody><template x-for="(allocation, allocationIndex) in row.allocations" :key="allocationIndex"><tr><td><label><span class="sr-only" x-text="'Distributor untuk ' + row.product_name"></span><select class="form-input" x-model="allocation.distributor_id"><option value="">Cari Distributor</option><template x-for="distributor in distributors" :key="distributor.id"><option :value="distributor.id" x-text="distributor.name"></option></template></select></label></td><td><input class="form-input" type="number" min="0" max="1000000" step="1" x-model="allocation.quantity" :aria-label="'Jumlah alokasi ' + row.product_name + ' ke ' + supplierName(allocation.distributor_id)"></td><td><button type="button" class="plan-remove-allocation" @click="row.allocations.splice(allocationIndex, 1)" :aria-label="'Hapus alokasi ' + supplierName(allocation.distributor_id)"><x-ui.icon name="trash" :size="18" /></button></td></tr></template></tbody></table></div>
                    <div class="plan-add-actions plan-add-actions--table"><button type="button" class="plan-dashed" @click="addAllocation(row)"><x-ui.icon name="add" :size="16" />Alokasi ke Distributor</button></div>
                </div>
            </section></template>
        </div>
        <section class="plan-panel" x-show="step === 3" x-cloak>
            <header><h2>Ringkasan dan validasi</h2><p>Pastikan semua kebutuhan induk teralokasi tepat sebelum membuat order internal.</p></header>
            <div class="plan-summary"><div><span>Produk</span><strong x-text="rows.length"></strong><x-ui.icon name="stock-box" :size="20" /></div><div><span>Total Kebutuhan Unit</span><strong x-text="units"></strong><x-ui.icon name="stock-box" :size="20" /></div><div><span>Distributor</span><strong x-text="supplierCount"></strong><x-ui.icon name="workshop" :size="20" /></div></div>
            <div class="plan-table-shell plan-table-scroll"><table class="plan-table"><thead><tr><th scope="col">Produk & Kebutuhan</th><th scope="col">Alokasi Distributor & Unit</th></tr></thead><tbody><template x-for="row in rows" :key="row.key"><tr><td><strong x-text="row.product_name + (row.color ? ' | ' + row.color : '')"></strong><small class="mt-1 block text-muted" x-text="'Kebutuhan ' + row.quantity + ' unit'"></small></td><td class="text-muted" x-text="allocationText(row)"></td></tr></template></tbody></table></div>
        </section>
        <footer class="plan-footer"><a href="{{ route('purchase-orders.index') }}" class="ui-button ui-button--cancel">Batalkan Edit</a><div><button type="button" class="ui-button ui-button--plain" x-show="step > 1" @click="goTo(step - 1)" :disabled="saving">Kembali</button><button type="button" class="ui-button ui-button--secondary" @click="save('draft')" :disabled="saving || importing" x-text="saving ? 'Menyimpan…' : 'Simpan Draft'">Simpan Draft</button><button type="button" class="ui-button" x-show="step < 3" @click="next()" :disabled="saving || importing">Lanjutkan<x-ui.icon name="arrow-right" :size="18" /></button><button type="button" class="ui-button" x-show="step === 3" x-cloak @click="save('finalize')" :disabled="saving || importing" x-text="saving ? 'Menyimpan…' : 'Finalisasi & Simpan'">Finalisasi & Simpan</button></div></footer>
        <x-ui.dialog name="plan-manual-product" title="Tambah Produk" description="Tambah produk untuk rencana ini tanpa mengubah katalog.">
            <form class="dialog-form plan-manual-form" @submit.prevent="applyManual()" @close-plan-manual.window="$el.closest('dialog').close()">
                <label class="form-field"><span class="form-field__label">Nama Produk</span><input class="form-input" x-model="manual.product_name" required maxlength="255"></label>
                <div class="form-grid"><label class="form-field"><span class="form-field__label">Warna Produk</span><input class="form-input" x-model="manual.color" maxlength="100"></label><label class="form-field"><span class="form-field__label">Tipe Produk</span><select class="form-input" x-model="manual.product_kind"><option value="daily">Harian</option><option value="non_daily">Non-Harian</option><option value="variation">Variasi</option></select></label><label class="form-field"><span class="form-field__label">Merk</span><input class="form-input" x-model="manual.brand" maxlength="255"></label><label class="form-field"><span class="form-field__label">Jenis Part</span><input class="form-input" x-model="manual.part_type" maxlength="255"></label></div>
                <footer class="dialog-actions"><button type="button" class="ui-button ui-button--cancel" @click="$el.closest('dialog').close()">Batalkan</button><button type="submit" class="ui-button">Tambah Produk Baru</button></footer>
            </form>
        </x-ui.dialog>
        <x-ui.dialog name="plan-import" title="Import Kebutuhan Produk" description="Gunakan template Excel .xlsx untuk menambahkan produk ke rencana. Katalog dan stok tidak akan diubah.">
            <div class="dialog-form"><a class="ui-button ui-button--secondary" href="{{ route('purchase-plans.template') }}">Unduh Template .xlsx</a><label class="form-field"><span class="form-field__label">File Excel (maksimal 5 MB)</span><input class="form-input" type="file" accept=".xlsx" :disabled="importing" @change="await importFile($event.target.files[0]); $event.target.value = ''; $el.closest('dialog').close()"></label><p x-show="importing" class="text-sm text-muted" role="status">Mengimpor produk…</p><p class="text-xs text-muted">Maksimal 100 produk per rencana. Isi kolom Kebutuhan dengan jumlah unit.</p></div>
        </x-ui.dialog>
    </div>
</x-app-layout>
