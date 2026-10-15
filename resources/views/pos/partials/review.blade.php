<x-ui.dialog name="pos-review" title="Ringkasan Transaksi" description="Periksa detail transaksi sebelum disimpan." class="pos-panel" close-icon="close">
    <form class="pos-review-body" @submit.prevent="checkout()">
        <section aria-label="Rincian belanja">
            <div class="flex justify-between gap-3 text-sm font-medium"><h3 x-text="'Produk ('+cart.length+' jenis)'"></h3><span x-text="units+' unit'"></span></div>
            <template x-for="item in cart" :key="item.id"><article class="pos-review-row"><div><h4 class="flex items-center gap-1.5"><x-ui.icon name="pos-box" :size="18" /><span class="truncate" x-text="item.name"></span></h4><p class="mt-1.5 text-xs text-muted" x-text="[item.color,item.brand,item.type].filter(Boolean).join(' · ')"></p><p class="mt-1.5 text-sm" x-text="item.quantity+' × '+money(item.price)"></p></div><strong x-text="money(item.quantity*item.price)"></strong></article></template>
            <dl class="mt-3 grid grid-cols-2 gap-3 text-sm"><dt class="text-muted">Subtotal</dt><dd class="text-right text-muted" x-text="money(total)"></dd><dt class="font-medium">Total Transaksi</dt><dd class="text-right font-semibold" x-text="money(total)"></dd></dl>
        </section>
        <div class="grid gap-3 border-t border-gray-200 pt-4">
            <x-ui.field name="paid_amount" label="Uang Diterima (Tunai)" type="number" x-model="paidAmount" x-ref="paidAmount" min="0" step="0.01" required placeholder="0" x-bind:disabled="busy" />
            <button type="button" @click="exactPayment()" :disabled="busy" class="justify-self-start text-sm underline">Uang Pas</button>
            <p class="flex justify-between gap-3 text-sm"><span>Kembalian</span><strong x-text="money(change)"></strong></p>
            <p x-cloak x-show="paidAmount !== '' && Number(paidAmount) < total" class="text-sm text-danger">Uang diterima belum mencukupi total transaksi.</p>
        </div>
        <div class="mt-auto grid gap-4">
            <p class="flex items-start gap-2 text-sm text-muted"><x-ui.icon name="info" :size="20" />Transaksi akan disimpan ke Histori Transaksi dan stok produk otomatis dikurangi.</p>
            <p x-cloak x-show="error" x-text="error" role="alert" class="rounded-xl bg-red-50 p-3 text-sm text-danger"></p>
            <button type="submit" class="ui-button w-full" :disabled="!canCheckout" x-text="busy ? 'Menyimpan…' : 'Simpan Transaksi'">Simpan Transaksi</button>
            <button type="button" @click="backToCart()" :disabled="busy" class="min-h-11 text-center font-medium text-muted">Kembali ke Keranjang</button>
        </div>
    </form>
</x-ui.dialog>
<x-ui.dialog name="pos-success" title="Transaksi Berhasil Dicatat!" description="Transaksi tersimpan dan stok sudah diperbarui." class="pos-success">
    <div class="dialog-form">
        <x-ui.icon name="success" :size="42" />
        <dl class="grid grid-cols-2 gap-4 text-sm"><dt>Total Transaksi</dt><dd class="text-right font-medium" x-text="money(result?.total)"></dd><dt>Nomor Invoice</dt><dd class="break-all text-right font-medium" x-text="result?.invoice"></dd><dt>Tanggal Transaksi</dt><dd class="text-right" x-text="result?.sold_at"></dd><dt>Kembalian</dt><dd class="text-right font-medium" x-text="money(result?.change)"></dd></dl>
        <button type="button" @click="newTransaction()" class="ui-button w-full">Transaksi Baru</button>
        <a :href="result?.detail_url || @js(route('transactions.index'))" class="ui-button ui-button--secondary w-full">Lihat Histori Transaksi</a>
    </div>
</x-ui.dialog>
