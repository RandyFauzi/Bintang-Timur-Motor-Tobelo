<x-app-layout>
    <div class="catalogue-page finance-page">
        <header class="catalogue-heading finance-heading">
            <div><h1>Laporan Keuangan</h1><p class="mt-2 text-sm text-muted">Rekap penjualan, modal barang, dan pengeluaran operasional.</p></div>
            <button type="button" class="ui-button" @click="$dispatch('open-dialog', { id: 'expense-form' })"><x-ui.icon name="add" :size="18" />Catat Pengeluaran</button>
        </header>
        <nav class="catalogue-tabs finance-periods" aria-label="Periode laporan keuangan">
            @foreach(['today'=>'Hari Ini', '7days'=>'7 Hari Terakhir', '30days'=>'30 Hari Terakhir'] as $value=>$label)
                <a href="{{ route('reports.finance', ['period'=>$value]) }}" @class(['catalogue-tab', 'is-active'=>$period === $value]) @if($period === $value) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
        <div class="finance-stats">
            <x-dashboard.stat-card title="Omzet Penjualan" :value="'Rp '.number_format($summary['revenue'],0,',','.')" :note="number_format($summary['transactions']).' transaksi selesai'" icon="banknote" />
            <x-dashboard.stat-card title="Modal Barang (HPP)" :value="'Rp '.number_format($summary['cogs'],0,',','.')" :note="'Laba kotor: Rp '.number_format($summary['gross_profit'],0,',','.')" icon="stock-box" />
            <x-dashboard.stat-card title="Pengeluaran Operasional" :value="'Rp '.number_format($totalExpenses,0,',','.')" note="Pengeluaran yang dicatat pada periode ini" icon="wallet" />
            <x-dashboard.stat-card title="Estimasi Laba Bersih" :value="'Rp '.number_format($netProfit,0,',','.')" note="Laba kotor dikurangi pengeluaran operasional" icon="banknote" :class="$netProfit < 0 ? 'finance-stat--loss' : 'finance-stat--profit'" />
        </div>
        <p class="mb-6 text-xs text-muted">Estimasi HPP memakai harga modal produk saat ini. Hasil dapat berubah jika harga modal produk diperbarui.</p>
        <div class="finance-panels">
            <x-dashboard.panel title="Catatan Pengeluaran" id="finance-expenses-title">
                <x-slot:filter><button type="button" class="panel-link" @click="$dispatch('open-dialog', { id: 'expense-form' })">Catat Pengeluaran <x-ui.icon name="chevron" :size="14" /></button></x-slot:filter>
                <p class="mb-4 text-xs text-muted">Lima catatan terbaru dalam periode yang dipilih.</p>
                <div class="table-scroll"><table class="data-table finance-table"><caption class="sr-only">Pengeluaran operasional terbaru</caption>
                    <thead><tr><th scope="col">Kategori &amp; Keterangan</th><th scope="col">Tanggal</th><th scope="col" class="text-right">Nominal</th><th scope="col" class="text-center w-12">Aksi</th></tr></thead>
                    <tbody>@forelse($recentExpenses as $expense)<tr>
                        <td><span class="font-medium">{{ $expense->category }}</span><span class="mt-1 block text-xs text-muted finance-description">{{ $expense->description ?: 'Tanpa keterangan' }}</span></td>
                        <td class="whitespace-nowrap">{{ $expense->expense_date->locale('id')->translatedFormat('d M Y') }}</td>
                        <td class="whitespace-nowrap text-right font-medium text-danger">Rp {{ number_format($expense->amount,0,',','.') }}</td>
                        <td class="whitespace-nowrap text-center"><button type="button" class="text-muted hover:text-red-600 transition-colors p-1" title="Hapus Pengeluaran" aria-label="Hapus pengeluaran {{ $expense->category }}" @click="$dispatch('open-delete-expense', { id: {{ $expense->id }}, category: '{{ addslashes($expense->category) }}', amount: 'Rp {{ number_format($expense->amount,0,',','.') }}' })"><x-ui.icon name="trash" :size="16" /></button></td>
                    </tr>@empty<tr><td colspan="4"><x-ui.empty-state title="Belum ada pengeluaran" description="Catat pengeluaran operasional atau pilih periode lain." /></td></tr>@endforelse</tbody>
                </table></div>
            </x-dashboard.panel>
            <x-dashboard.panel title="10 Produk Terlaris" id="finance-products-title">
                <x-slot:filter><a href="{{ route('transactions.index') }}" class="panel-link">Histori Transaksi <x-ui.icon name="chevron" :size="14" /></a></x-slot:filter>
                <p class="mb-4 text-xs text-muted">Peringkat berdasarkan jumlah unit pada transaksi selesai.</p>
                <div class="table-scroll"><table class="data-table finance-table"><caption class="sr-only">Produk terlaris pada periode terpilih</caption>
                    <thead><tr><th scope="col">Produk</th><th scope="col" class="text-right">Terjual</th><th scope="col" class="text-right">Nilai Item</th></tr></thead>
                    <tbody>@forelse($topProducts as $product)<tr><td class="font-medium finance-description">{{ $product->product_name }}</td><td class="text-right">{{ number_format($product->total_sold) }} unit</td><td class="whitespace-nowrap text-right font-medium">Rp {{ number_format($product->total_revenue,0,',','.') }}</td></tr>@empty<tr><td colspan="3"><x-ui.empty-state title="Belum ada penjualan" description="Produk terlaris akan tampil setelah ada transaksi selesai pada periode ini." /></td></tr>@endforelse</tbody>
                </table></div>
                <p class="mt-4 text-xs text-muted">Nilai item memakai subtotal produk sebelum diskon atau pajak tingkat transaksi.</p>
            </x-dashboard.panel>
        </div>
        <x-ui.dialog name="expense-form" title="Catat Pengeluaran" description="Masukkan pengeluaran operasional bengkel." :show="old('_dialog') === 'expense-form'" close-icon="close">
            <form method="POST" action="{{ route('reports.expenses.store') }}" class="dialog-form" @submit="submitting = true">
                @csrf<input type="hidden" name="_dialog" value="expense-form">
                <x-ui.field name="expense_date" label="Tanggal Pengeluaran" type="date" :value="now()->toDateString()" required />
                <x-ui.field name="category" label="Kategori Pengeluaran"><select id="category" name="category" class="form-input" required aria-describedby="category-help" aria-invalid="{{ $errors->has('category') ? 'true' : 'false' }}"><option value="">Pilih kategori</option>@foreach(['Listrik & Air','Gaji Karyawan','Sewa Tempat','Transport & Bensin','Konsumsi','Lain-lain'] as $category)<option value="{{ $category }}" @selected(old('category') === $category)>{{ $category }}</option>@endforeach</select></x-ui.field>
                <x-ui.field name="amount" label="Nominal (Rp)" type="number" min="1" max="9999999999999.99" step="0.01" placeholder="Contoh: 150000" required />
                <x-ui.field name="description" label="Keterangan (Opsional)"><textarea id="description" name="description" class="form-input" rows="3" maxlength="2000" aria-describedby="description-help" placeholder="Contoh: Pembayaran listrik bengkel">{{ old('description') }}</textarea></x-ui.field>
                <footer class="dialog-actions"><button type="button" class="ui-button ui-button--cancel" @click="$el.closest('dialog').close()">Batal</button><button type="submit" class="ui-button" :disabled="submitting" x-text="submitting ? 'Menyimpan…' : 'Simpan Pengeluaran'">Simpan Pengeluaran</button></footer>
            </form>
        </x-ui.dialog>
        <x-ui.dialog name="delete-expense" title="Hapus Catatan Pengeluaran" description="Catatan pengeluaran operasional ini akan dihapus dari laporan keuangan.">
            <form method="POST" class="dialog-form" x-data="{ expenseCat: '', expenseAmount: '', deleteUrl: '' }" :action="deleteUrl" @open-delete-expense.window="expenseCat = $event.detail.category; expenseAmount = $event.detail.amount; deleteUrl = '{{ url('reports/expenses') }}/' + $event.detail.id; $dispatch('open-dialog', { id: 'delete-expense' })" @submit="submitting = true">
                @csrf @method('DELETE')
                <p class="text-sm">Hapus pengeluaran <strong x-text="expenseCat"></strong> sebesar <strong x-text="expenseAmount"></strong>?</p>
                <footer class="dialog-actions">
                    <button type="button" class="ui-button ui-button--cancel" @click="$el.closest('dialog').close()">Batalkan</button>
                    <button type="submit" class="ui-button ui-button--danger" :disabled="submitting || !deleteUrl" x-text="submitting ? 'Menghapus…' : 'Ya, Hapus Pengeluaran'">Ya, Hapus Pengeluaran</button>
                </footer>
            </form>
        </x-ui.dialog>
    </div>
</x-app-layout>
