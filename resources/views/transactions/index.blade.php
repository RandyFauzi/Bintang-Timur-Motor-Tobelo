<x-app-layout>
    <div class="catalogue-page">
        <header class="catalogue-heading flex-wrap">
            <div><h1>Histori Transaksi</h1><p class="mt-2 text-sm text-muted">Telusuri penjualan, rincian belanja, dan pembayaran pelanggan.</p></div>
            <a href="{{ route('pos.index') }}" class="ui-button"><x-ui.icon name="add" :size="18" />Transaksi Baru</a>
        </header>
        <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach([['Total Transaksi', number_format($summary['transactions']), 'Sesuai filter yang dipilih', 'history'], ['Nilai Penjualan', 'Rp '.number_format($summary['revenue'],2,',','.'), 'Hanya transaksi selesai', 'banknote'], ['Transaksi Selesai', number_format($summary['completed']), 'Sesuai filter yang dipilih', 'cart'], ['Transaksi Batal', number_format($summary['cancelled']), 'Tidak dihitung dalam penjualan', 'return']] as [$title, $value, $note, $icon])
                <x-dashboard.stat-card :title="$title" :value="$value" :note="$note" :icon="$icon" />
            @endforeach
        </div>
        <section class="catalogue-surface" aria-label="Histori transaksi">
            <form method="GET" action="{{ route('transactions.index') }}">
                <x-ui.catalogue-toolbar export-route="transactions.export" />
                <div class="grid gap-4 p-4 sm:grid-cols-2 xl:grid-cols-4">
                    <x-ui.field name="start_date" label="Tanggal Mulai" type="date" :value="request('start_date')" />
                    <x-ui.field name="end_date" label="Tanggal Akhir" type="date" :value="request('end_date')" />
                    <x-ui.field name="status" label="Status Transaksi"><select id="status" name="status"><option value="">Semua status</option>@foreach(['completed'=>'Selesai', 'cancelled'=>'Dibatalkan'] as $value=>$label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select></x-ui.field>
                    <x-ui.field name="payment_status" label="Status Pembayaran"><select id="payment_status" name="payment_status"><option value="">Semua pembayaran</option>@foreach(['paid'=>'Lunas', 'unpaid'=>'Belum Dibayar', 'partial'=>'Dibayar Sebagian'] as $value=>$label)<option value="{{ $value }}" @selected(request('payment_status') === $value)>{{ $label }}</option>@endforeach</select></x-ui.field>
                    <x-ui.field name="payment_method" label="Metode Pembayaran"><select id="payment_method" name="payment_method"><option value="">Semua metode</option>@foreach($paymentMethods as $method)<option value="{{ $method }}" @selected(request('payment_method') === $method)>{{ $method === 'cash' ? 'Tunai' : ucfirst($method) }}</option>@endforeach</select></x-ui.field>
                    <x-ui.field name="user_id" label="Kasir"><select id="user_id" name="user_id"><option value="">Semua kasir</option>@foreach($cashiers as $cashier)<option value="{{ $cashier->id }}" @selected((string) request('user_id') === (string) $cashier->id)>{{ $cashier->name }}</option>@endforeach</select></x-ui.field>
                    <div class="flex flex-wrap items-end gap-3 sm:col-span-2"><button class="ui-button" type="submit"><x-ui.icon name="filter" :size="18" />Terapkan Filter</button><a class="ui-button ui-button--secondary" href="{{ route('transactions.index') }}">Reset Filter</a></div>
                </div>
                <p class="px-4 pb-4 text-xs text-muted">Cari berdasarkan invoice, pelanggan, telepon, nama produk, SKU, atau barcode. Kosongkan tanggal untuk seluruh periode.</p>
            </form>
            @if(request('view') === 'cards')
                <div class="catalogue-card-grid">
                    @forelse($transactions as $transaction)
                        <article class="product-card">
                            <header><div><h2><a href="{{ route('transactions.show', $transaction) }}" class="underline">{{ $transaction->invoice_number }}</a></h2><p class="mt-2 text-sm text-muted">{{ $transaction->sold_at?->format('d/m/Y H:i') ?? 'Tanggal tidak tersedia' }}</p></div><x-ui.transaction-status :value="$transaction->status" /></header>
                            <dl class="product-card__details">
                                <dt>Pelanggan</dt><dd>{{ $transaction->customer_name ?: 'Pelanggan Umum' }}</dd>
                                <dt>Kasir</dt><dd>{{ $transaction->user?->name ?? '–' }}</dd>
                                <dt>Jumlah Unit</dt><dd>{{ $transaction->items_sum_quantity ?? 0 }}</dd>
                                <dt>Metode</dt><dd>{{ $transaction->payments->pluck('method')->unique()->map(fn($method) => $method === 'cash' ? 'Tunai' : ucfirst($method))->join(', ') ?: '–' }}</dd>
                                <dt>Pembayaran</dt><dd><x-ui.transaction-status :value="$transaction->payment_status" payment /></dd>
                                <dt>Total</dt><dd class="font-semibold">Rp {{ number_format($transaction->grand_total,2,',','.') }}</dd>
                            </dl>
                            <a href="{{ route('transactions.show', $transaction) }}" class="ui-button ui-button--secondary w-full">Lihat Detail</a>
                        </article>
                    @empty
                        <x-ui.empty-state title="Transaksi tidak ditemukan" description="Belum ada transaksi atau tidak ada yang cocok dengan filter. Coba ubah pencarian atau reset filter." />
                    @endforelse
                </div>
            @else
                <div class="catalogue-table-scroll"><table class="catalogue-table">
                    <caption class="sr-only">Daftar histori transaksi penjualan</caption>
                    <thead><tr>@foreach(['Invoice / Tanggal','Pelanggan','Kasir','Unit','Metode','Status','Pembayaran','Total','Aksi'] as $heading)<th scope="col">{{ $heading }}</th>@endforeach</tr></thead>
                    <tbody>@forelse($transactions as $transaction)<tr>
                        <td><a class="font-medium underline" href="{{ route('transactions.show', $transaction) }}">{{ $transaction->invoice_number }}</a><span class="mt-1 block text-muted">{{ $transaction->sold_at?->format('d/m/Y H:i') ?? 'Tanggal tidak tersedia' }}</span></td>
                        <td>{{ $transaction->customer_name ?: 'Pelanggan Umum' }}</td><td>{{ $transaction->user?->name ?? '–' }}</td><td>{{ $transaction->items_sum_quantity ?? 0 }}</td>
                        <td>{{ $transaction->payments->pluck('method')->unique()->map(fn($method) => $method === 'cash' ? 'Tunai' : ucfirst($method))->join(', ') ?: '–' }}</td>
                        <td><x-ui.transaction-status :value="$transaction->status" /></td><td><x-ui.transaction-status :value="$transaction->payment_status" payment /></td>
                        <td class="whitespace-nowrap font-medium">Rp {{ number_format($transaction->grand_total,2,',','.') }}</td><td><a class="edit-link" href="{{ route('transactions.show', $transaction) }}" aria-label="Detail {{ $transaction->invoice_number }}">Detail</a></td>
                    </tr>@empty<tr><td colspan="9"><x-ui.empty-state title="Transaksi tidak ditemukan" description="Belum ada transaksi atau tidak ada yang cocok dengan filter. Coba ubah pencarian atau reset filter." /></td></tr>@endforelse</tbody>
                </table></div>
            @endif
            <x-ui.pagination :paginator="$transactions" />
        </section>
    </div>
</x-app-layout>
