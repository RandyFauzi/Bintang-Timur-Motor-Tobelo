<x-ui.dialog name="observation-form" :title="$editing ? 'Edit Pengamatan Pengerjaan' : 'Catat Pengerjaan Kompetitor'" description="Satu pengamatan bisa berisi beberapa jenis pengerjaan." :show="$editing !== null || request()->boolean('create') || old('_dialog') === 'observation-form'" wide>
    <form method="POST" action="{{ $editing ? route('reports.observations.update',$editing) : route('reports.observations.store') }}" class="dialog-form" @submit="submitting = true">
        @csrf @if($editing) @method('PATCH') @endif<input type="hidden" name="_dialog" value="observation-form">
        <div class="form-grid form-grid--three">
            <x-ui.field name="workshop_id" label="Nama Bengkel">
                <select id="workshop_id" name="workshop_id" class="form-input" x-model="form.workshop_id" required><option value="">Pilih bengkel</option><template x-for="workshop in workshops" :key="workshop.id"><option :value="String(workshop.id)" x-text="workshop.name"></option></template></select>
                <button type="button" class="lookup-add-link" @click="openLookup('workshops')">+ Tambah Bengkel</button>
            </x-ui.field>
            <x-ui.field name="observed_on" label="Tanggal Pengamatan" type="date" x-model="form.observed_on" max="{{ now()->toDateString() }}" required />
            <x-ui.field name="observed_hour" label="Jam Pengamatan">
                <select id="observed_hour" name="observed_hour" class="form-input" x-model="form.observed_hour" required><option value="">Pilih jam</option>@foreach([11,14] as $hour)<option value="{{ $hour }}">Jam {{ sprintf('%02d.00',$hour) }}</option>@endforeach</select>
            </x-ui.field>
        </div>
        <fieldset class="observation-types"><legend>Jenis Pengerjaan</legend>
            <div class="work-type-options"><template x-for="type in types" :key="type.id"><label class="work-type-choice" :class="{ 'is-selected': hasType(type.id) }"><input type="checkbox" :checked="hasType(type.id)" @change="toggleType(type.id, $event.target.checked)"><span x-text="type.name"></span></label></template></div>
            <button type="button" class="lookup-add-button" @click="openLookup('types')">+ Tambah Jenis Pengerjaan Baru</button>
            @error('items')<p class="field-error">{{ $message }}</p>@enderror
        </fieldset>
        <section class="work-quantities" x-show="form.items.length > 0">
            <h3 class="text-sm font-medium">Jumlah per jenis</h3><p class="mt-1 text-xs text-muted">Isi jumlah untuk setiap jenis yang dipilih.</p>
            <template x-for="(item, index) in form.items" :key="item.work_type_id">
                <div class="work-quantity-row">
                    <span x-text="typeName(item.work_type_id)"></span>
                    <input type="hidden" :name="'items['+index+'][work_type_id]'" :value="item.work_type_id">
                    <div class="quantity-stepper"><button type="button" @click="item.quantity = Math.max(1, Number(item.quantity) - 1)" :aria-label="'Kurangi '+typeName(item.work_type_id)">−</button><input type="number" min="1" max="100000" :name="'items['+index+'][quantity]'" x-model.number="item.quantity" :aria-label="'Jumlah '+typeName(item.work_type_id)" required><button type="button" @click="item.quantity = Math.min(100000, Number(item.quantity) + 1)" :aria-label="'Tambah '+typeName(item.work_type_id)">+</button></div>
                </div>
            </template>
            <div class="work-total"><span>Total Pengerjaan</span><strong x-text="total"></strong></div>
        </section>
        <footer class="dialog-actions"><button type="button" class="ui-button ui-button--cancel" @click="$el.closest('dialog').close()">Batalkan</button><button type="submit" class="ui-button" :disabled="submitting || form.items.length === 0" x-text="submitting ? 'Menyimpan…' : 'Simpan Pengerjaan'">Simpan Pengerjaan</button></footer>
    </form>
</x-ui.dialog>
<x-ui.dialog name="report-lookup" title="Tambah Data Baru" description="Buat satu atau lebih nama tanpa meninggalkan pengamatan.">
    <form class="dialog-form" @submit.prevent="saveLookup()">
        <p class="font-medium" x-text="lookupKind === 'workshops' ? 'Tambah Bengkel Baru' : 'Tambah Jenis Pengerjaan Baru'"></p>
        <template x-for="(_, index) in newNames" :key="index"><label class="form-field"><span class="form-field__label" x-text="(lookupKind === 'workshops' ? 'Nama Bengkel ' : 'Nama Pengerjaan ') + (index+1)"></span><input class="form-input" x-model="newNames[index]" required maxlength="255"></label></template>
        <p class="field-error" x-show="lookupError" x-text="lookupError" role="alert"></p>
        <button type="button" class="lookup-add-button" @click="if (newNames.length < 20) newNames.push('')">+ Tambah Nama</button>
        <footer class="dialog-actions"><button type="button" class="ui-button ui-button--cancel" @click="$el.closest('dialog').close()" :disabled="lookupBusy">Batalkan</button><button type="submit" class="ui-button" :disabled="lookupBusy" x-text="lookupBusy ? 'Menyimpan…' : 'Simpan'">Simpan</button></footer>
    </form>
</x-ui.dialog>
