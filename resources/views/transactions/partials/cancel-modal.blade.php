<x-ui.dialog name="cancel-transaction-modal" title="Batalkan Transaksi" description="Transaksi yang dibatalkan akan ditandai sebagai Batal dan stok produk akan dikembalikan ke inventaris." close-icon="close">
    <div x-data="{
        actionUrl: '',
        invoiceNumber: '',
        reason: '',
        init() {
            window.addEventListener('open-cancel-modal', (e) => {
                this.actionUrl = '{{ url('/transactions') }}/' + e.detail.id + '/cancel';
                this.invoiceNumber = e.detail.invoice;
                this.reason = '';
                $dispatch('open-dialog', { id: 'cancel-transaction-modal' });
            });
        }
    }">
        <form method="POST" :action="actionUrl" class="dialog-form" @submit="if (!reason.trim()) { $event.preventDefault(); return; }">
            @csrf
            <div class="mb-4 text-sm text-gray-700 dark:text-gray-300">
                Apakah Anda yakin ingin membatalkan transaksi <strong x-text="invoiceNumber"></strong>?
            </div>
            
            <div class="mb-4">
                <label for="cancellation-reason" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Alasan Pembatalan <span class="text-red-500">*</span>
                </label>
                <textarea id="cancellation-reason" name="reason" x-model="reason" rows="3" required minlength="3" maxlength="1000"
                    class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:border-gray-500 focus:outline-none focus:ring-1 focus:ring-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                    placeholder="Masukkan alasan pembatalan transaksi..."></textarea>
                @error('reason')
                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-300">
                <strong>Catatan:</strong> Transaksi tidak akan dihapus dari histori penjualan untuk kebutuhan audit trail. Stok produk yang terjual akan otomatis dikembalikan ke inventaris.
            </div>

            <footer class="dialog-actions">
                <button type="button" class="ui-button ui-button--plain" @click="$el.closest('dialog').close()">Batal</button>
                <button type="submit" class="ui-button ui-button--danger" :disabled="!reason.trim() || reason.trim().length < 3">
                    Ya, Batalkan Transaksi
                </button>
            </footer>
        </form>
    </div>
</x-ui.dialog>
