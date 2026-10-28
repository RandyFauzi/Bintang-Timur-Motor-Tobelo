@php($serviceDurations = $services->mapWithKeys(fn (\App\Models\ShippingService $service): array => [$service->id => $editing?->shipping_service_id === $service->id ? $editing->duration_days : $service->duration_days]))
<x-ui.dialog name="shipment-form" :title="$editing ? 'Edit Pengiriman' : 'Tambah Pengiriman'" description="Catat informasi cargo. Estimasi tiba dihitung dari tanggal berangkat dan durasi jasa." :show="$editing !== null || request()->boolean('create') || old('_dialog') === 'shipment-form'">
    <form method="POST" action="{{ $editing ? route('shipments.update',$editing) : route('shipments.store') }}" class="dialog-form" x-data="shipmentForm(@js($serviceDurations), @js(old('shipping_service_id', $editing?->shipping_service_id ?? '')), @js(old('departed_on', $editing?->departed_on->toDateString() ?? \App\Models\Shipment::today()->toDateString())))" @submit="submitting = true">
        @csrf @if($editing) @method('PATCH')<input type="hidden" name="version" value="{{ old('version', $editing->version) }}"><input type="hidden" name="_shipment_id" value="{{ $editing->id }}">@endif
        <input type="hidden" name="_dialog" value="shipment-form">
        @if($services->where('is_active', true)->isEmpty() && !$editing)<p class="shipping-warning">Tambahkan jasa pengiriman aktif terlebih dahulu. <button type="button" class="underline font-medium" @click="$el.closest('dialog').close(); $dispatch('open-dialog',{id:'shipping-services'})">Tambah Jasa Pengiriman</button></p>@endif
        <div class="form-grid">
            <x-ui.field name="cargo_name" label="Nama Cargo / Barang" :value="$editing?->cargo_name" required maxlength="255" placeholder="Contoh: Suku cadang motor" />
            <x-ui.field name="reference" label="Nomor Resi / Referensi (opsional)" :value="$editing?->reference" maxlength="100" hint="Dibuat otomatis jika dikosongkan." />
            <x-ui.field name="shipping_service_id" label="Jasa Pengiriman"><select id="shipping_service_id" name="shipping_service_id" class="form-input" x-model="serviceId" required><option value="">Pilih jasa pengiriman</option>@foreach($services->filter(fn (\App\Models\ShippingService $service): bool => $service->is_active || $service->id === $editing?->shipping_service_id) as $service)<option value="{{ $service->id }}" @selected((string) old('shipping_service_id',$editing?->shipping_service_id) === (string) $service->id)>{{ $editing?->shipping_service_id === $service->id ? $editing->service_name : $service->name }} · {{ $serviceDurations[$service->id] }} hari</option>@endforeach</select></x-ui.field>
            <x-ui.field name="vessel_name" label="Nama Kapal (opsional)" :value="$editing?->vessel_name" maxlength="255" />
            <x-ui.field name="origin_port" label="Pelabuhan / Kota Asal" :value="$editing?->origin_port" required maxlength="255" placeholder="Contoh: Surabaya" />
            <x-ui.field name="destination_port" label="Pelabuhan / Kota Tujuan" :value="$editing?->destination_port ?? config('shipping.destination_port')" required maxlength="255" />
            <x-ui.field name="departed_on" label="Tanggal Berangkat" type="date" x-model="departedOn" :value="$editing?->departed_on->toDateString() ?? \App\Models\Shipment::today()->toDateString()" required />
            <x-ui.field name="package_count" label="Jumlah Koli" type="number" min="1" max="1000000" :value="old('package_count', $editing?->package_count ?? 1)" required />
            <x-ui.field name="shipping_cost" label="Ongkir Ekspedisi (Rp)" type="number" min="0" step="0.01" :value="old('shipping_cost', $editing && $editing->shipping_cost_cents > 0 ? number_format($editing->shipping_cost_cents / 100, 2, '.', '') : '')" placeholder="Contoh: 150000" />
            @if($editing && $editing->distributorInvoices->isNotEmpty())
                <div class="form-field"><span class="form-field__label">Distributor</span><p class="break-words text-sm">{{ $editing->distributorInvoices->pluck('purchaseOrder.distributor_name')->unique()->join(', ') }}</p></div>
            @endif
        </div>
        <p class="shipping-estimate" aria-live="polite">Estimasi tiba: <strong x-text="estimatedArrival"></strong></p>
        <x-ui.field name="destination_address" label="Alamat Tujuan"><textarea id="destination_address" name="destination_address" class="form-input" rows="3" maxlength="1000">{{ old('destination_address',$editing?->destination_address ?? config('shipping.destination_address')) }}</textarea></x-ui.field>
        <x-ui.field name="notes" label="Catatan (opsional)"><textarea id="notes" name="notes" class="form-input" rows="3" maxlength="2000">{{ old('notes',$editing?->notes) }}</textarea></x-ui.field>
        <p class="text-xs text-muted">Mendekati tujuan mulai 2 hari sebelum estimasi tiba. Tanggal yang terlewati menjadi terlambat; penerimaan tetap perlu konfirmasi.</p>
        <footer class="dialog-actions">
            @if($editing)
                <button type="button" class="ui-button ui-button--danger mr-auto" @click="$el.closest('dialog').close(); $dispatch('open-dialog', { id: 'shipment-delete' })">
                    <x-ui.icon name="trash" :size="16" /> Hapus Pengiriman
                </button>
            @endif
            <button type="button" class="ui-button ui-button--cancel" @click="$el.closest('dialog').close()">Batalkan</button>
            <button type="submit" class="ui-button" :disabled="submitting || @js($services->where('is_active',true)->isEmpty() && !$editing)">Simpan Pengiriman</button>
        </footer>
    </form>
</x-ui.dialog>
<x-ui.dialog name="shipping-services" title="Jasa Pengiriman" description="Kelola nama jasa dan durasi estimasi perjalanan cargo." :show="request()->boolean('services') || $editingService !== null || old('_dialog') === 'shipping-services'" wide>
    <div class="dialog-form">
        @if($services->isNotEmpty())
            <div class="shipping-service-list">
                @foreach($services as $service)
                    <div class="flex items-center justify-between gap-3">
                        <span><strong>{{ $service->name }}</strong><small>{{ $service->duration_days }} hari · {{ $service->is_active ? 'Aktif' : 'Nonaktif' }}</small></span>
                        <div class="flex items-center gap-2">
                            <a href="{{ route('shipments.index', ['service_edit'=>$service->id]) }}" class="edit-link">Edit <x-ui.icon name="edit" :size="16" /></a>
                            <button type="button" class="text-muted hover:text-red-600 transition-colors p-1" title="Hapus Jasa" aria-label="Hapus {{ $service->name }}" @click="$dispatch('open-delete-service', { id: {{ $service->id }}, name: '{{ addslashes($service->name) }}' })">
                                <x-ui.icon name="trash" :size="16" />
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
        <form method="POST" action="{{ $editingService ? route('shipping-services.update',$editingService) : route('shipping-services.store') }}" class="dialog-form" @submit="submitting = true">
            @csrf @if($editingService) @method('PATCH')<input type="hidden" name="_service_id" value="{{ $editingService->id }}">@endif<input type="hidden" name="_dialog" value="shipping-services">
            <h3 class="font-medium">{{ $editingService ? 'Edit Jasa Pengiriman' : 'Tambah Jasa Pengiriman' }}</h3>
            <x-ui.field name="name" id="shipping-service-name" label="Nama Jasa Pengiriman" :value="$editingService?->name" required maxlength="255" />
            <div class="form-grid"><x-ui.field name="duration" label="Durasi Estimasi" type="number" min="1" max="3640" :value="$editingService?->duration_days ?? 2" required /><x-ui.field name="duration_unit" label="Satuan"><select id="duration_unit" name="duration_unit" class="form-input"><option value="days" @selected(old('duration_unit',$editingService ? 'days' : 'weeks') === 'days')>Hari</option><option value="weeks" @selected(old('duration_unit',$editingService ? 'days' : 'weeks') === 'weeks')>Minggu</option></select></x-ui.field></div>
            <x-ui.field name="is_active" label="Status Jasa"><select id="is_active" name="is_active" class="form-input"><option value="1" @selected(old('is_active', $editingService?->is_active ?? true))>Aktif</option><option value="0" @selected(!old('is_active', $editingService?->is_active ?? true))>Nonaktif</option></select></x-ui.field>
            <p class="text-xs text-muted">Contoh: 2 minggu = 14 hari. Perubahan jasa hanya berlaku untuk pengiriman baru; estimasi pengiriman lama tidak berubah.</p>
            <footer class="dialog-actions">
                @if($editingService)
                    <button type="button" class="ui-button ui-button--danger mr-auto" @click="$dispatch('open-delete-service', { id: {{ $editingService->id }}, name: '{{ addslashes($editingService->name) }}' })">
                        <x-ui.icon name="trash" :size="16" /> Hapus Jasa
                    </button>
                @endif
                <button type="button" class="ui-button ui-button--cancel" @click="$el.closest('dialog').close()">Tutup</button>
                @if($editingService)<a href="{{ route('shipments.index',['services'=>1]) }}" class="ui-button ui-button--secondary">Tambah Baru</a>@endif
                <button type="submit" class="ui-button" :disabled="submitting">Simpan Jasa</button>
            </footer>
        </form>
    </div>
</x-ui.dialog>
@if($selected || $editing)
@php($targetShipment = $editing ?? $selected)
<x-ui.dialog name="shipment-delete" title="Hapus Pengiriman Cargo" description="Data pelacakan pengiriman ini akan dihapus. Faktur distributor terkait akan dilepas dan tetap tersimpan aman.">
    <form method="POST" action="{{ route('shipments.destroy', $targetShipment) }}" class="dialog-form" @submit="submitting = true">
        @csrf @method('DELETE')
        <input type="hidden" name="version" value="{{ $targetShipment->version }}">
        <p class="text-sm">Hapus pengiriman cargo <strong class="break-words">{{ $targetShipment->cargo_name }}</strong> (Resi: {{ $targetShipment->reference }})?</p>
        @if($targetShipment->distributorInvoices->isNotEmpty())
            <p class="rounded-xl bg-amber-50 p-3 text-xs text-amber-800">Perhatian: Cargo ini terhubung ke {{ $targetShipment->distributorInvoices->count() }} faktur distributor. Faktur-faktur tersebut akan dipisahkan dari cargo ini dan tetap tersimpan aman.</p>
        @endif
        <footer class="dialog-actions">
            <button type="button" class="ui-button ui-button--cancel" @click="$el.closest('dialog').close()">Batalkan</button>
            <button type="submit" class="ui-button ui-button--danger" :disabled="submitting" x-text="submitting ? 'Menghapus…' : 'Ya, Hapus Pengiriman'">Ya, Hapus Pengiriman</button>
        </footer>
    </form>
</x-ui.dialog>
@endif
<x-ui.dialog name="delete-service" title="Hapus Jasa Pengiriman" description="Jasa pengiriman hanya dapat dihapus jika belum digunakan pada pengiriman manapun.">
    <form method="POST" class="dialog-form" x-data="{ serviceName: '', deleteUrl: '' }" :action="deleteUrl" @open-delete-service.window="serviceName = $event.detail.name; deleteUrl = '{{ url('shipping-services') }}/' + $event.detail.id; $dispatch('open-dialog', { id: 'delete-service' })" @submit="submitting = true">
        @csrf @method('DELETE')
        <p class="text-sm">Hapus jasa pengiriman <strong x-text="serviceName"></strong>?</p>
        <footer class="dialog-actions">
            <button type="button" class="ui-button ui-button--cancel" @click="$el.closest('dialog').close()">Batalkan</button>
            <button type="submit" class="ui-button ui-button--danger" :disabled="submitting || !deleteUrl" x-text="submitting ? 'Menghapus…' : 'Ya, Hapus Jasa'">Ya, Hapus Jasa</button>
        </footer>
    </form>
</x-ui.dialog>
@if($selected && !$selected->delivered_at)
<x-ui.dialog name="shipment-arrive" title="Konfirmasi Cargo Sudah Sampai" description="Konfirmasikan hanya jika barang sudah benar-benar diterima." :show="old('_dialog') === 'shipment-arrive'">
    <form method="POST" action="{{ route('shipments.arrive',$selected) }}" class="dialog-form" @submit="submitting = true">@csrf @method('PATCH')<input type="hidden" name="_dialog" value="shipment-arrive"><input type="hidden" name="version" value="{{ $selected->version }}"><p class="text-sm">Cargo <strong>{{ $selected->cargo_name }}</strong> dengan referensi {{ $selected->reference }} akan ditandai sudah sampai. Nama Anda dan waktu konfirmasi akan tercatat.</p><footer class="dialog-actions"><button type="button" class="ui-button ui-button--cancel" @click="$el.closest('dialog').close()">Batalkan</button><button type="submit" class="ui-button" :disabled="submitting">Ya, Sudah Diterima</button></footer></form>
</x-ui.dialog>
@endif
