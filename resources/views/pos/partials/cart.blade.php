<x-ui.dialog name="pos-cart" title="Keranjang" class="pos-panel pos-cart-sheet" close-icon="close">
    <div class="pos-panel-body">
        <div class="pos-cart-items">
            <p class="px-2 text-sm text-muted" x-text="cart.length+' jenis produk · '+units+' unit'"></p>
            <template x-for="item in cart" :key="item.id">
                <article class="pos-cart-item">
                    <div class="flex items-start justify-between gap-3"><div class="min-w-0 flex-1"><h3 class="flex items-start gap-1.5 font-medium leading-snug"><x-ui.icon name="pos-box" :size="18" class="shrink-0 mt-0.5" /><span class="break-words" x-text="item.name" :title="item.name"></span></h3><p class="mt-1 text-xs text-muted" x-text="[item.color,item.brand,item.type].filter(Boolean).join(' · ')"></p></div><button type="button" @click="remove(item.id)" :disabled="busy" :aria-label="'Hapus '+item.name" class="grid min-h-8 min-w-8 place-items-center shrink-0"><x-ui.icon name="trash" :size="18" /></button></div>
                    <div class="mt-3 flex flex-wrap items-center justify-between gap-3"><strong x-text="money(item.price)"></strong><div class="pos-quantity"><button type="button" @click="setQuantity(item.id,item.quantity-1)" :disabled="busy || item.quantity <= 1" :aria-label="'Kurangi '+item.name"><x-ui.icon name="minus-square" :size="16" /></button><input type="number" min="1" :max="item.available" step="1" :value="item.quantity" :disabled="busy" @input="updateQuantityInput(item.id,$event.target)" @change="updateQuantityInput(item.id,$event.target,true)" :aria-label="'Jumlah '+item.name"><button type="button" @click="setQuantity(item.id,item.quantity+1)" :disabled="busy || item.quantity >= item.available" :aria-label="'Tambah '+item.name"><x-ui.icon name="add-square" :size="16" /></button></div></div>
                    <p class="mt-2 text-xs text-muted" x-text="'Stok tersedia: '+item.available+' unit'"></p>
                    <p x-cloak x-show="item.quantity > item.available" class="mt-2 text-xs text-danger">Stok berubah. Kurangi jumlah atau hapus produk ini.</p>
                </article>
            </template>
            <div x-show="!cart.length" class="my-auto p-8 text-center"><p class="font-medium">Keranjang masih kosong</p><p class="mt-2 text-sm text-muted">Pilih produk dari katalog untuk memulai transaksi.</p></div>
        </div>
        <footer class="pos-cart-footer"><div class="flex items-center justify-between gap-3 font-semibold"><span>Total Tagihan</span><span x-text="money(total)" aria-live="polite"></span></div><button type="button" class="ui-button w-full" @click="review()" :disabled="!canCheckout">Proses Transaksi <x-ui.icon name="pos-cart" :size="18" /></button><button type="button" class="min-h-11 text-center font-medium text-muted" @click="$el.closest('dialog').close()">Kembali ke Kasir</button></footer>
    </div>
</x-ui.dialog>
