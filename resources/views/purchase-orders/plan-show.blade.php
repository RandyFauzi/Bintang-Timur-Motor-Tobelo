<x-app-layout>
    <div class="catalogue-page plan-page note-page" x-data="purchaseOrderNotes(@js($orders), @js($shippingServices), @js(['today' => $shippingToday, 'planId' => $plan->id, 'replanUrl' => route('purchase-orders.create'), 'finalizeShipmentUrl' => route('purchase-plans.shipment.finalize', $plan)]))">
        <header class="plan-heading"><a class="plan-back" href="{{ route('purchase-orders.index') }}">← Kembali ke daftar Pre Order</a><h1>{{ $plan->reference }}</h1><p>Dibuat {{ $plan->planned_on->translatedFormat('d M Y') }} · {{ $plan->name }}</p></header>
        @if($plan->orders->isNotEmpty() && $plan->orders->every(fn ($order) => $order->archived_at !== null))<p class="note-notice">Pengadaan ini ada di Arsip. Nota, tagihan, dan pengiriman tetap tersimpan serta dapat diproses. <a class="underline" href="{{ route('purchase-orders.index', ['archived' => 1]) }}">Lihat Arsip</a></p>@endif
        @if($plan->status === 'draft')<a class="ui-button w-fit" href="{{ route('purchase-plans.edit', $plan) }}">Lanjutkan Draft</a>@endif
        <div class="plan-summary note-summary"><div><span>Produk</span><strong>{{ count($plan->items) }}</strong><x-ui.icon name="note-product" :size="20" /></div><div><span>Total Kebutuhan Unit</span><strong>{{ number_format(collect($plan->items)->sum('quantity'), 0, ',', '.') }}</strong><x-ui.icon name="note-units" :size="20" /></div><div><span>Distributor</span><strong>{{ $plan->orders->count() }}</strong><x-ui.icon name="note-distributor" :size="20" /></div></div>
        @if($plan->status === 'finalized')
        <section class="note-workspace" aria-label="Kelola nota distributor">
            <nav class="note-tabs" aria-label="Bagian pengadaan"><button type="button" :class="{'is-active': tab === 'distributors'}" :aria-current="tab === 'distributors' ? 'page' : null" @click="tab = 'distributors'">Distributor</button><button type="button" :class="{'is-active': tab === 'actions'}" :aria-current="tab === 'actions' ? 'page' : null" @click="tab = 'actions'">Perlu Tindakan<span class="note-action-count" x-show="shortages.length" x-cloak x-text="shortages.length" :aria-label="shortages.length + ' produk belum terpenuhi'"></span></button></nav>
            <div class="note-filters" x-show="tab === 'distributors'">
                <x-ui.multiselect name="note_distributor" id="note-distributor-filter" label="Semua Distributor" :options="collect([(object) ['id' => '', 'name' => 'Semua Distributor']])->concat($plan->orders->unique('distributor_id')->map(fn ($order): object => (object) ['id' => $order->distributor_id, 'name' => $order->distributor_name]))" :selected="[]" :multiple="false" :submit-on-apply="false" :dropdown="true" icon="workshop" x-model="distributorSelection" />
                <x-ui.multiselect name="note_status" id="note-status-filter" label="Semua Status Nota" :options="collect(['' => 'Semua Status Nota', 'unissued' => 'Nota Belum Terbit', 'issued' => 'Nota Sudah Terbit', 'shipping' => 'Dalam Pengiriman'])->map(fn (string $label, string $value): object => (object) ['id' => $value, 'name' => $label])->values()" :selected="[]" :multiple="false" :submit-on-apply="false" :dropdown="true" icon="status-filter" x-model="noteStatusSelection" />
            </div>
            <label class="catalogue-search note-search"><x-ui.icon name="search" :size="18" /><input type="search" x-model.debounce.200ms="search" placeholder="Cari apapun..." aria-label="Cari distributor, nomor nota, atau produk"></label>
            <p class="note-notice" role="status" aria-live="polite" x-show="notice" x-cloak x-text="notice"></p>

            <div class="flex flex-wrap items-center justify-between gap-3 p-3 bg-blue-50 border border-blue-200 rounded-xl my-3" x-show="tab === 'distributors' && eligibleFinalizeOrders.length > 0" x-cloak>
                <label class="inline-flex items-center gap-2 text-sm font-medium text-blue-900 cursor-pointer">
                    <input type="checkbox" class="rounded border-blue-300 text-blue-600 focus:ring-blue-500" :checked="allEligibleFinalizeSelected" x-effect="$el.indeterminate = someEligibleFinalizeSelected && !allEligibleFinalizeSelected" @change="toggleSelectAllFinalize($event.target.checked)">
                    <span>Pilih Semua Distributor Siap Kirim (<span x-text="eligibleFinalizeOrders.length"></span>)</span>
                </label>
                <div class="flex items-center gap-2">
                    <span class="text-xs text-blue-700 font-medium" x-show="selectedFinalizeOrders.length > 0" x-text="selectedFinalizeOrders.length + ' distributor dipilih'"></span>
                    <button type="button" class="ui-button text-sm py-1.5 px-3" :disabled="selectedFinalizeOrders.length === 0" @click="openBatchFinalize()">
                        <span>Satukan & Finalisasi Pengiriman</span>
                        <span x-show="selectedFinalizeOrders.length > 0" x-text="'(' + selectedFinalizeOrders.length + ')'"></span>
                        <x-ui.icon name="ship" :size="16" />
                    </button>
                </div>
            </div>

            <div class="note-cards" x-show="tab === 'distributors'">
                <template x-for="order in filteredOrders" :key="order.id"><article class="note-card">
                    <header class="note-card__heading">
                        <button type="button" class="plan-collapse" @click="order.collapsed = !order.collapsed" :aria-expanded="!order.collapsed" :aria-controls="'note-content-' + order.id" :aria-label="'Tampilkan nota ' + order.distributor_name"><x-ui.icon name="chevron" :size="18" x-bind:class="order.collapsed ? '' : 'rotate-90'" /></button>
                        <label class="inline-flex items-center gap-2 cursor-pointer" x-show="canFinalize(order)" x-cloak @click.stop title="Pilih distributor untuk finalisasi pengiriman bersama">
                            <input type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" :checked="selectedFinalizeOrderIds.includes(order.id)" @change="toggleFinalizeOrder(order.id, $event.target.checked)" :aria-label="'Pilih pengiriman ' + order.distributor_name">
                        </label>
                        <div class="flex-1 min-w-0"><h2 x-text="order.distributor_name"></h2><p x-show="!order.note_issued" x-text="order.items.length + ' Produk | ' + units(order) + ' Unit'"></p></div>
                        <span class="note-badge" :class="order.finalized ? (order.shipment_status === 'delivered' ? 'note-badge--delivered' : 'note-badge--shipping') : order.note_issued ? 'note-badge--issued' : 'note-badge--pending'" x-text="badge(order)"></span>
                    </header>
                    <form @submit.prevent="save(order)" x-show="!order.collapsed" :id="'note-content-' + order.id" class="note-card__content">
                        <div class="note-fields">
                            <label class="form-field"><span class="form-field__label">Status Nota</span><select class="form-input" :value="order.note_issued ? '1' : '0'" @change="order.note_issued = $event.target.value === '1'; toggleIssued(order)" :disabled="!order.can_edit || order.finalized || (order.saved.note_issued && !order.editing) || order.saving"><option value="0">Belum Terbit</option><option value="1">Sudah Terbit</option></select></label>
                            <label class="form-field"><span class="form-field__label">Nomor Nota</span><input type="text" class="form-input" x-model="order.supplier_note_number" maxlength="255" placeholder="Tulis Nomor Invoice" :disabled="!order.note_issued" :readonly="!order.editing || !order.can_edit || order.saving" :required="order.note_issued"></label>
                            <label class="form-field"><span class="form-field__label">Tanggal Nota</span><input type="date" class="form-input" x-model="order.supplier_note_on" :disabled="!order.note_issued" :readonly="!order.editing || !order.can_edit || order.saving" :required="order.note_issued"></label>
                            <label class="form-field"><span class="form-field__label">Total Tagihan Nota (Rp)</span><input type="number" class="form-input" x-model="order.supplier_note_total" min="0" max="90000000000000" step="0.01" placeholder="Contoh: 125000" :disabled="!order.note_issued" :readonly="!order.editing || !order.can_edit || order.saving" :required="order.note_issued"><span class="text-xs text-muted">Isi total sesuai nota, tanpa pemisah ribuan.</span></label>
                        </div>
                        <div role="alert" class="note-errors" x-show="order.errors.length" x-cloak><template x-for="(error, index) in order.errors" :key="index"><p x-text="error"></p></template></div>
                        <div class="note-table-scroll"><table class="note-table"><thead><tr><th scope="col">Produk</th><th scope="col">QTY ORDER</th><th scope="col">QTY INVOICE</th></tr></thead><tbody><template x-for="item in order.items" :key="item.id"><tr><td x-text="item.product_name + (item.color ? ' | ' + item.color : '')"></td><td x-text="item.quantity"></td><td><input class="form-input note-quantity" type="number" min="0" max="1000000" step="1" x-model="item.invoice_quantity" x-show="order.editing && order.note_issued && order.can_edit" :disabled="!order.editing || !order.note_issued || !order.can_edit || order.saving" :required="order.editing && order.note_issued" :aria-label="'Jumlah pada nota ' + order.distributor_name + ' untuk ' + item.product_name"><span x-show="!order.editing || !order.note_issued || !order.can_edit" x-text="order.note_issued && item.invoice_quantity !== null ? item.invoice_quantity : '-'"></span><small class="note-difference" x-show="order.note_issued && difference(item)" x-text="difference(item)"></small></td></tr></template></tbody></table></div>
                        <footer class="note-card__actions" x-show="!order.finalized && order.can_edit && (dirty(order) || (order.editing && order.saved.note_issued))" x-cloak><button type="button" class="ui-button ui-button--secondary" @click="cancel(order)" :disabled="order.saving">Batalkan Perubahan</button><button type="submit" class="ui-button" :disabled="order.saving" x-text="order.saving ? 'Menyimpan…' : 'Simpan Perubahan'">Simpan Perubahan</button></footer>
                        <footer class="note-card__actions" x-show="order.saved.note_issued && !order.editing && !order.finalized" x-cloak><button type="button" class="ui-button ui-button--secondary" @click="edit(order)" :disabled="!order.can_edit">Edit<x-ui.icon name="edit" :size="18" /></button><button type="button" class="ui-button" :disabled="!canFinalize(order)" @click="openFinalize(order)">Finalisasi Pengiriman<x-ui.icon name="ship" :size="18" /></button></footer>
                        <p class="text-xs text-muted" x-show="order.saved.note_issued && !order.finalized && !order.saved.items.some(item => Number(item.invoice_quantity) > 0)" x-cloak>Nota tanpa barang tidak dapat dikirim. Pilih kekurangan di tab Perlu Tindakan untuk pengadaan lanjutan.</p>
                        <div class="note-card__links" x-show="order.invoice_url" x-cloak><a :href="order.invoice_url" class="underline underline-offset-4">Lihat Tagihan</a><a :href="order.shipment_url" x-show="order.shipment_url" class="underline underline-offset-4">Lacak Pengiriman</a></div>
                    </form>
                </article></template>
                <div x-show="!filteredOrders.length" x-cloak><x-ui.empty-state title="Distributor tidak ditemukan" description="Ubah pencarian atau pilihan filter nota." /></div>
            </div>
            <div class="note-actions-panel" x-show="tab === 'actions'" x-cloak>
                <div class="note-table-scroll"><table class="note-table note-shortage-table"><thead><tr><th scope="col"><label class="inline-flex min-h-11 min-w-11 items-center justify-center"><input type="checkbox" :checked="allVisibleShortagesSelected" x-effect="$el.indeterminate = someVisibleShortagesSelected && !allVisibleShortagesSelected" :aria-checked="someVisibleShortagesSelected && !allVisibleShortagesSelected ? 'mixed' : String(allVisibleShortagesSelected)" :disabled="!visibleShortages.length" @change="selectVisibleShortages($event.target.checked)"><span class="sr-only">Pilih semua produk pada hasil pencarian</span></label></th><th scope="col">Produk</th><th scope="col">KEBUTUHAN BELUM TERPENUHI</th></tr></thead><tbody><template x-for="item in visibleShortages" :key="item.source_index"><tr><td><input type="checkbox" x-model="selected" :value="String(item.source_index)" :aria-label="'Order lagi ' + item.product_name"></td><td x-text="item.product_name + (item.color ? ' | ' + item.color : '')"></td><td><strong x-text="item.quantity + ' unit'"></strong></td></tr></template></tbody></table></div>
                <div x-show="!visibleShortages.length"><x-ui.empty-state title="Tidak ada kekurangan yang ditemukan" description="Kekurangan muncul setelah jumlah pada nota distributor disimpan." /></div>
                <footer class="note-replan-footer"><span>Produk dipilih <strong x-text="selectedShortages.length"></strong></span><button type="button" class="ui-button" @click="createReplan()" :disabled="!selectedShortages.length">Buat Perencanaan Baru</button></footer>
            </div>
        </section>
        <x-ui.dialog name="finalize-plan-shipment" title="Finalisasi Pengiriman?" class="note-finalize-dialog">
            <form class="dialog-form" @submit.prevent="finalize()" @close-plan-shipment.window="$el.closest('dialog').close()">
                <p class="text-sm text-muted">Setelah difinalisasi, nota tidak dapat diedit dan pengiriman dapat dilacak melalui modul Pelacakan Pengiriman.</p>
                <div class="flex items-center justify-between text-xs font-semibold uppercase tracking-wider text-primary">
                    <span x-text="selectedFinalizeOrders.length > 1 ? 'Pengiriman Bersama (' + selectedFinalizeOrders.length + ' Distributor)' : 'Distributor Terpilih'"></span>
                    <span x-text="totalBatchUnits + ' total unit barang'"></span>
                </div>

                <div class="space-y-3 max-h-80 overflow-y-auto pr-1">
                    <template x-for="order in selectedFinalizeOrders" :key="order.id">
                        <section class="border rounded-xl p-3 bg-gray-50/70" :aria-label="'Preview pesanan ' + order.distributor_name">
                            <header class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 pb-2">
                                <div>
                                    <h3 class="font-semibold text-gray-900" x-text="order.distributor_name"></h3>
                                    <p class="text-xs text-muted" x-text="order.reference + ' · Nota: ' + (order.saved.supplier_note_number || '-')"></p>
                                </div>
                                <div class="text-right">
                                    <span class="text-xs text-muted block">Total Tagihan Nota</span>
                                    <strong class="text-sm font-medium text-gray-800" x-text="money(order.saved.supplier_note_total)"></strong>
                                </div>
                            </header>

                            <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3 bg-white p-3 rounded-lg border border-gray-200" x-show="shipment.orderInputs && shipment.orderInputs[order.id]">
                                <label class="form-field mb-0">
                                    <span class="form-field__label text-xs font-medium text-gray-700">Jumlah Koli (<span x-text="order.distributor_name"></span>)</span>
                                    <input type="number" class="form-input text-sm" x-model.number="shipment.orderInputs[order.id].package_count" min="1" max="1000000" step="1" required :readonly="finalizing" placeholder="Contoh: 1">
                                    <span class="text-xs text-muted">Koli khusus distributor ini.</span>
                                </label>
                                <label class="form-field mb-0">
                                    <span class="form-field__label text-xs font-medium text-gray-700">Ongkir Ekspedisi Rp (<span x-text="order.distributor_name"></span>)</span>
                                    <input type="number" class="form-input text-sm" x-model="shipment.orderInputs[order.id].shipping_cost" min="0" max="90000000000000" step="0.01" placeholder="Contoh: 150000" :readonly="finalizing">
                                    <span class="text-xs text-muted">Ongkir khusus distributor ini.</span>
                                </label>
                            </div>

                            <ul class="mt-2 divide-y divide-gray-100 text-xs">
                                <template x-for="item in order.saved.items.filter(i => Number(i.invoice_quantity) > 0)" :key="item.id">
                                    <li class="py-1 flex items-center justify-between text-muted">
                                        <span x-text="item.product_name + (item.color ? ' (' + item.color + ')' : '')"></span>
                                        <strong class="text-gray-700" x-text="item.invoice_quantity + ' ' + (item.unit || 'unit')"></strong>
                                    </li>
                                </template>
                            </ul>
                        </section>
                    </template>
                </div>

                <div class="border-t border-gray-200 pt-3 space-y-3">
                    <fieldset class="form-field note-cargo-picker" :disabled="finalizing || !services.length">
                        <legend class="form-field__label">Jasa Cargo</legend>
                        <x-ui.multiselect name="shipping_service_id" id="plan-cargo-filter" label="Pilih Jasa Cargo" :options="$shippingServices->map(fn ($service): object => (object) ['id' => $service->id, 'name' => $service->name.' · '.$service->duration_days.' hari'])" :selected="[]" :multiple="false" :submit-on-apply="false" :dropdown="true" icon="ship" x-model="cargoSelection" />
                    </fieldset>
                    @if($shippingServices->isEmpty())<p class="text-sm text-danger">Belum ada jasa cargo aktif. Tambahkan melalui <a class="underline" href="{{ route('shipments.index') }}">Pelacakan Pengiriman → Jasa Pengiriman</a>.</p>@endif

                    <div class="form-grid">
                        <label class="form-field"><span class="form-field__label">Pelabuhan / Kota Asal</span><input class="form-input" x-model="shipment.origin_port" maxlength="255" required :readonly="finalizing"></label>
                        <label class="form-field"><span class="form-field__label">Tanggal Berangkat</span><input class="form-input" type="date" x-model="shipment.departed_on" required min="2000-01-01" max="2100-12-31" :readonly="finalizing"></label>
                    </div>

                    <label class="form-field">
                        <span class="form-field__label">Catatan Pengiriman (Kargo)</span>
                        <textarea class="form-input" x-model="shipment.notes" rows="2" maxlength="1000" placeholder="Tulis catatan pengiriman kargo (opsional)..." :readonly="finalizing"></textarea>
                        <span class="text-xs text-muted">Catatan disimpan ke riwayat pelacakan cargo pengiriman.</span>
                    </label>

                    <div class="p-3 bg-gray-100 rounded-xl flex flex-wrap items-center justify-between gap-3 text-sm">
                        <div>
                            <span class="text-xs text-muted block">Total Kemasan Gabungan</span>
                            <strong class="text-gray-900" x-text="totalBatchPackageCount + ' koli'"></strong>
                        </div>
                        <div>
                            <span class="text-xs text-muted block">Total Ongkir Ekspedisi</span>
                            <strong class="text-gray-900" x-text="money(totalBatchShippingCost)"></strong>
                        </div>
                        <div>
                            <span class="text-xs text-muted block">Tujuan Pengiriman</span>
                            <strong class="text-gray-900">{{ config('shipping.destination_port') }}</strong>
                        </div>
                    </div>
                </div>

                <div class="note-errors" role="alert" x-show="finalizeErrors.length"><template x-for="(error, index) in finalizeErrors" :key="index"><p x-text="error"></p></template></div>
                <footer class="note-card__actions">
                    <button type="button" class="ui-button ui-button--cancel" @click="$el.closest('dialog').close()" :disabled="finalizing">Batalkan</button>
                    <button type="submit" class="ui-button" :disabled="finalizing || !services.length" x-text="finalizing ? 'Memproses…' : (selectedFinalizeOrders.length > 1 ? 'Ya, Finalisasi ' + selectedFinalizeOrders.length + ' Distributor' : 'Ya, Finalisasi Pengiriman')"></button>
                </footer>
            </form>
        </x-ui.dialog>
        @endif
    </div>
</x-app-layout>
