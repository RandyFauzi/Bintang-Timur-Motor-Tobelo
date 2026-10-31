<x-app-layout>
<div class="catalogue-page procurement-detail-page grid gap-5">
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
        <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="border-y bg-gray-50 text-muted"><tr>@foreach(['Invoice / PO','Distributor','Tanggal Faktur / Masuk','Jatuh Tempo','Total Tagihan','Dibayarkan','Sisa','Status','Retur / Selisih'] as $label)<th class="whitespace-nowrap p-4 font-medium">{{ $label }}</th>@endforeach<th class="whitespace-nowrap p-4 font-medium min-w-[220px]">Aksi</th></tr></thead><tbody>
        @forelse($invoices as $invoice)<tr class="border-b hover:bg-gray-50">
            <td class="p-4">
                <a class="whitespace-nowrap font-medium underline underline-offset-4" href="{{ route('distributor-invoices.show',$invoice) }}">{{ $invoice->reference }}</a>
                @if($invoice->purchaseOrder)
                    <p class="mt-1 text-xs text-muted">{{ $invoice->purchaseOrder->reference }}</p>
                @else
                    <span class="mt-1 inline-block text-[11px] font-medium text-slate-500 bg-slate-100 px-1.5 py-0.5 rounded">Manual / Tanpa PO</span>
                @endif
                @if($invoice->supplier_reference)
                    <p class="mt-1 text-xs text-muted">Nota: {{ $invoice->supplier_reference }}</p>
                @endif
            </td>
            <td class="p-4 font-medium">{{ $invoice->distributor_name }}</td>
            <td class="whitespace-nowrap p-4">
                <p>Faktur: {{ $invoice->invoiced_on?->format('d/m/Y') ?? 'Belum diisi' }}</p>
                <p class="mt-1 text-xs text-muted">Masuk: {{ $invoice->receivedAt()?->format('d/m/Y') ?? ($invoice->purchaseOrder ? 'Menunggu approval kargo' : '-') }}</p>
                @if($invoice->shipment)
                    <a class="mt-1 inline-block text-xs underline" href="{{ route('shipments.index',['shipment'=>$invoice->shipment_id]) }}#shipment-detail">{{ $invoice->shipment->reference }}</a>
                @endif
            </td>
            <td class="whitespace-nowrap p-4">
                {{ $invoice->due_on->format('d/m/Y') }}
                <p class="mt-1 text-xs text-muted">{{ $invoice->timingLabel() }}</p>
            </td>
            <td class="whitespace-nowrap p-4">Rp {{ number_format($invoice->total_cents/100,2,',','.') }}</td>
            <td class="whitespace-nowrap p-4">Rp {{ number_format($invoice->paid_cents/100,2,',','.') }}</td>
            <td class="whitespace-nowrap p-4 font-medium">Rp {{ number_format($invoice->remainingCents()/100,2,',','.') }}</td>
            <td class="p-4">
                <x-ui.invoice-status :status="$invoice->billingStatus()" />
                @if($invoice->billingStatus() === 'overdue')
                    <p class="mt-2 text-xs text-muted">{{ \App\Models\DistributorInvoice::STATUSES[$invoice->status()] }}</p>
                @endif
            </td>
            <td class="min-w-48 max-w-xs p-4">
                <p class="break-words">{{ \Illuminate\Support\Str::limit($invoice->discrepancy_notes ?: '-',100) }}</p>
                @if($invoice->claim_amount_cents > 0)
                    <p class="mt-2 text-xs text-muted">Klaim dicatat: Rp {{ number_format($invoice->claim_amount_cents/100,2,',','.') }}</p>
                @endif
            </td>
            <td class="whitespace-nowrap p-4">
                <div class="inline-flex items-center gap-1.5 whitespace-nowrap">
                    @if($invoice->remainingCents() > 0)
                        <a class="ui-button whitespace-nowrap inline-flex items-center gap-1 !min-h-8 !py-1 !px-2.5 !text-xs !bg-emerald-700 hover:!bg-emerald-800 !text-white font-medium shadow-sm" href="{{ route('distributor-invoices.show',['distributorInvoice'=>$invoice->id,'pay'=>1]) }}">
                            <x-ui.icon name="wallet" :size="13" class="brightness-0 invert" />Bayar
                        </a>
                    @endif
                    <a class="ui-button ui-button--secondary whitespace-nowrap inline-flex items-center gap-1 !min-h-8 !py-1 !px-2.5 !text-xs"
                        href="{{ route('distributor-invoices.show', ['distributorInvoice' => $invoice->id, 'edit' => 1]) }}"
                        title="Edit Data Tagihan"
                        @click.prevent="$dispatch('open-edit-invoice', {{ json_encode([
                            'id' => $invoice->id,
                            'updateUrl' => route('distributor-invoices.update', $invoice),
                            'version' => $invoice->version,
                            'isManual' => $invoice->isManual(),
                            'distributor_id' => (string) ($invoice->distributor_id ?? ''),
                            'distributor_name' => $invoice->distributor_name,
                            'reference' => $invoice->reference,
                            'invoiced_on' => $invoice->invoiced_on?->toDateString() ?? '',
                            'due_on' => $invoice->due_on->toDateString(),
                            'total' => number_format($invoice->total_cents / 100, 2, '.', ''),
                            'min_total' => number_format($invoice->paid_cents / 100, 2, '.', ''),
                            'supplier_reference' => $invoice->supplier_reference ?? '',
                            'shipment_id' => (string) ($invoice->shipment_id ?? ''),
                            'notes' => $invoice->notes ?? '',
                            'discrepancy_notes' => $invoice->discrepancy_notes ?? '',
                            'claim_amount' => number_format($invoice->claim_amount_cents / 100, 2, '.', ''),
                            'note_issued' => (bool) ($invoice->purchaseOrder?->note_issued),
                        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) }})">
                        <x-ui.icon name="edit" :size="13" />Edit Data
                    </a>
                    <a class="ui-button ui-button--secondary whitespace-nowrap inline-flex items-center !min-h-8 !py-1 !px-2.5 !text-xs" href="{{ route('distributor-invoices.show',$invoice) }}">Detail</a>
                    <button type="button" class="ui-button ui-button--secondary whitespace-nowrap inline-flex items-center justify-center !min-h-8 !h-8 !w-8 !p-0 !text-xs text-red-600 hover:text-red-700 hover:bg-red-50 hover:border-red-300"
                        title="Hapus Tagihan"
                        aria-label="Hapus tagihan {{ $invoice->reference }}"
                        @click="$dispatch('open-delete-invoice', {{ json_encode([
                            'id' => $invoice->id,
                            'reference' => $invoice->reference,
                            'distributor' => $invoice->distributor_name,
                            'po' => $invoice->purchaseOrder?->reference ?? 'Manual',
                            'version' => $invoice->version,
                            'hasPayments' => $invoice->paid_cents > 0,
                        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) }})">
                        <x-ui.icon name="trash" :size="14" />
                    </button>
                </div>
            </td>
        </tr>
        @empty<tr><td colspan="10"><x-ui.empty-state title="Belum ada tagihan yang ditemukan" description="Tagihan otomatis muncul setelah PO dibuat, atau tambahkan tagihan manual / import file Excel." /></td></tr>@endforelse
        </tbody></table></div><x-ui.pagination :paginator="$invoices" />
    </section>

    <!-- Dialog: Tambah Tagihan Manual -->
    <x-ui.dialog name="invoice-create" title="Tambah Tagihan Manual" description="Catat faktur tagihan langsung dari distributor tanpa melalui alur PO." :show="old('_dialog') === 'invoice-create'" wide>
        <form method="POST" action="{{ route('distributor-invoices.store') }}" class="dialog-form" @submit="submitting = true">
            @csrf
            <input type="hidden" name="_dialog" value="invoice-create">
            <div class="form-grid">
                <x-ui.field name="distributor_id" label="Distributor" required>
                    <select id="create_distributor_id" name="distributor_id" class="form-input" required>
                        <option value="">Pilih Distributor</option>
                        @foreach($distributors as $distributor)
                            <option value="{{ $distributor->id }}" @selected(old('distributor_id') == $distributor->id)>{{ $distributor->name }}</option>
                        @endforeach
                    </select>
                </x-ui.field>
                <x-ui.field name="reference" label="Nomor Invoice (Opsional)" :value="old('reference')" maxlength="255" placeholder="Otomatis jika dikosongkan" hint="Kosongkan untuk penomoran otomatis sistem (INV-MAN-...)." />
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
        <form method="POST" action="{{ route('distributor-invoices.import') }}" enctype="multipart/form-data" class="dialog-form" @submit="submitting = true">
            @csrf
            <input type="hidden" name="_dialog" value="invoice-import">
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
            <x-ui.field name="file" label="Pilih File Excel (.xlsx)" required>
                <input class="form-input" type="file" name="file" accept=".xlsx" required :disabled="submitting">
            </x-ui.field>
            <footer class="dialog-actions">
                <button type="button" class="ui-button ui-button--cancel" @click="$el.closest('dialog').close()">Batalkan</button>
                <button type="submit" class="ui-button" :disabled="submitting" x-text="submitting ? 'Mengimpor…' : 'Mulai Import'">Mulai Import</button>
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
                reference: '',
                invoiced_on: '',
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
            <input type="hidden" name="version" :value="version">

            <div class="form-grid">
                <template x-if="isManual">
                    <x-ui.field name="distributor_id" label="Distributor" required>
                        <select id="edit_distributor_id_idx" name="distributor_id" class="form-input" x-model="distributor_id" required>
                            <option value="">Pilih Distributor</option>
                            @foreach($distributors as $distributor)
                                <option value="{{ $distributor->id }}">{{ $distributor->name }}</option>
                            @endforeach
                        </select>
                    </x-ui.field>
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
</div>
</x-app-layout>
