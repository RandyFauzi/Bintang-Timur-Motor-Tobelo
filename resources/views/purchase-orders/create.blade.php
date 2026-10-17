<x-app-layout>
<div class="catalogue-page grid gap-5">
    <header class="catalogue-heading !mb-0 !block"><a class="text-sm text-muted underline" href="{{ route('purchase-orders.index') }}">Kembali ke daftar PO</a><h1 class="mt-3">Buat PO / Order Barang</h1><p class="mt-2 text-sm text-muted">Pilih distributor, lalu ketik nama barang sesuai nota atau pesanan. Nama barang PO berdiri sendiri dari Produk & Stok.</p></header>
    @if($errors->any())<div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-danger"><p class="font-medium">Periksa kembali isian berikut:</p><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form action="{{ route('purchase-orders.store') }}" method="POST" class="grid gap-5" x-data="purchaseOrderForm(@js($initialItems))" @submit="submitting = true">
        @csrf<input type="hidden" name="request_id" value="{{ is_string(old('request_id')) ? old('request_id') : $requestId }}">
        <section class="catalogue-surface p-5"><div class="form-grid !grid-cols-1 sm:!grid-cols-2">
            <x-ui.field name="distributor_id" label="Distributor"><select id="distributor_id" name="distributor_id" required class="form-input"><option value="">Pilih distributor</option>@foreach($distributors as $distributor)<option value="{{ $distributor->id }}" @selected((string) $selectedDistributor === (string) $distributor->id)>{{ $distributor->name }}</option>@endforeach</select></x-ui.field>
            <div class="flex items-end"><button type="button" class="ui-button ui-button--secondary" @click="$dispatch('open-dialog',{id:'po-distributor'})">Tambah Distributor</button></div>
            <x-ui.field name="ordered_on" label="Tanggal Order" type="date" :value="now()->toDateString()" required />
            <x-ui.field name="expected_on" label="Estimasi Datang (opsional)" type="date" />
            <x-ui.field name="payment_term_months" label="Tempo Pembayaran"><select name="payment_term_months" id="payment_term_months" class="form-input"><option value="3" @selected(old('payment_term_months',3) == 3)>3 bulan (default)</option><option value="2" @selected(old('payment_term_months') == 2)>2 bulan</option></select></x-ui.field>
            <x-ui.field name="due_on" label="Jatuh Tempo Khusus (opsional)" type="date" hint="Jika kosong, dihitung dari tanggal order sesuai tempo pembayaran. Tagihan dibuat otomatis." />
            <div class="form-grid__full"><x-ui.field name="notes" label="Catatan (opsional)" maxlength="2000" /></div>
        </div></section>
        <section class="catalogue-surface p-5">
            <div class="flex flex-wrap items-center justify-between gap-3"><div><h2 class="text-base font-medium">Barang yang Dipesan</h2><p class="mt-2 text-sm text-muted">Isi manual sesuai nama distributor. Tidak harus sama dengan nama produk di stok toko.</p></div><button type="button" class="ui-button ui-button--secondary" @click="add()">Tambah Baris Barang</button></div>
            <p x-show="error" x-text="error" class="mt-3 text-sm text-danger" role="alert"></p>
            <div class="mt-4 overflow-x-auto"><table class="w-full text-left text-sm"><thead class="border-y bg-gray-50"><tr><th class="p-3">Nama Barang / Kode Distributor</th><th class="p-3">Satuan</th><th class="p-3">Jumlah</th><th class="p-3">Harga Satuan (Rp)</th><th class="p-3">Subtotal</th><th class="p-3">Aksi</th></tr></thead>
            <tbody><template x-for="(row,index) in rows" :key="row.key"><tr class="border-b">
                <td class="min-w-64 p-3"><input type="text" required maxlength="255" class="form-input w-full" :aria-label="'Nama barang baris ' + (index+1)" :name="`items[${index}][product_name]`" x-model="row.product_name" placeholder="Nama sesuai pesanan distributor"><input type="text" maxlength="255" class="form-input mt-2 w-full" :aria-label="'Kode distributor baris ' + (index+1)" :name="`items[${index}][sku]`" x-model="row.sku" placeholder="Kode distributor (opsional)"></td>
                <td class="p-3"><input type="text" required maxlength="50" class="form-input w-24" :aria-label="'Satuan baris ' + (index+1)" :name="`items[${index}][unit]`" x-model="row.unit" placeholder="pcs / dus"></td>
                <td class="p-3"><input type="number" required min="1" max="1000000" step="1" class="form-input w-24" :aria-label="'Jumlah baris ' + (index+1)" :name="`items[${index}][quantity]`" x-model="row.quantity"></td>
                <td class="p-3"><input type="number" required min="0" max="999999999" step="0.01" class="form-input w-36" :aria-label="'Harga baris ' + (index+1)" :name="`items[${index}][unit_price]`" x-model="row.unit_price"></td>
                <td class="whitespace-nowrap p-3" x-text="money(row.quantity * row.unit_price)"></td><td class="p-3"><button type="button" class="ui-button ui-button--secondary" :aria-label="'Hapus baris ' + (index+1)" @click="rows.splice(index,1)">Hapus</button></td>
            </tr></template></tbody></table></div>
            <p x-show="!rows.length" class="py-8 text-center text-muted">Klik Tambah Baris Barang, lalu isi nama, jumlah, dan harga secara manual.</p>
        <div class="mt-5 flex flex-wrap justify-between gap-3"><span class="text-sm text-muted" x-text="rows.length + ' jenis barang'"></span><p class="text-lg font-medium">Total: <span x-text="money(total)"></span></p></div></section>
        <footer class="flex justify-end gap-3"><a class="ui-button ui-button--secondary" href="{{ route('purchase-orders.index') }}">Batalkan</a><button type="submit" class="ui-button" :disabled="submitting || !rows.length" x-text="submitting ? 'Menyimpan…' : 'Simpan PO'">Simpan PO</button></footer>
    </form>
    <x-ui.dialog name="po-distributor" title="Tambah Distributor" description="Daftarkan pemasok baru sebelum membuat PO." :show="old('_dialog') === 'po-distributor'">
        <form method="POST" action="{{ route('purchase-orders.distributors.store') }}" class="dialog-form" @submit="submitting=true">@csrf<input type="hidden" name="_dialog" value="po-distributor"><p class="text-sm text-muted">Daftarkan distributor sebelum mengisi pesanan. Setelah disimpan, distributor otomatis terpilih.</p><div class="form-grid !grid-cols-1 sm:!grid-cols-2"><x-ui.field name="name" label="Nama Distributor" required maxlength="255" /><x-ui.field name="phone" label="Telepon" maxlength="50" /><x-ui.field name="company_name" label="Perusahaan" maxlength="255" /><x-ui.field name="city" label="Kota" maxlength="255" /></div><footer class="dialog-actions"><button type="button" class="ui-button ui-button--cancel" @click="$el.closest('dialog').close()">Batalkan</button><button class="ui-button" :disabled="submitting">Simpan Distributor</button></footer></form>
    </x-ui.dialog>
</div>
</x-app-layout>
