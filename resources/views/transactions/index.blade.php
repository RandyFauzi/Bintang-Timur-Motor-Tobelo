<x-app-layout>
    <div class="catalogue-page history-page">
        <header class="catalogue-heading"><div><h1>Histori Transaksi</h1><p class="mt-2 text-sm text-muted">Telusuri penjualan, rincian belanja, dan pembayaran pelanggan.</p></div></header>
        <div class="history-summary">
            <x-dashboard.stat-card title="Nilai Penjualan" :value="'Rp '.number_format($summary['revenue'],2,',','.')" note="Sesuai filter yang dipilih · Hanya transaksi selesai" icon="history-banknote" />
            <x-dashboard.stat-card title="Total Transaksi" :value="number_format($summary['transactions']).' Transaksi'" note="Sesuai filter yang dipilih" icon="stock-box" />
            <x-dashboard.stat-card title="Transaksi Batal" :value="number_format($summary['cancelled'])" note="Tidak dihitung dalam penjualan" icon="return-units" />
        </div>
        <section class="catalogue-surface" aria-label="Histori transaksi">
            <form method="GET" action="{{ route('transactions.index') }}">
                <x-ui.catalogue-toolbar export-route="transactions.export" create-label="Tambah Transaksi" :create-href="route('pos.index')" />
                <div class="history-filters">
                    <details class="history-date"><summary><x-ui.icon name="calendar" :size="14" />{{ request()->filled('start_date') || request()->filled('end_date') ? 'Tanggal Dipilih' : 'Semua Tanggal' }}<x-ui.icon name="chevron" :size="12" /></summary><div class="history-date-panel"><x-ui.field name="start_date" label="Tanggal Mulai" type="date" :value="request('start_date')" /><x-ui.field name="end_date" label="Tanggal Akhir" type="date" :value="request('end_date')" /><button type="submit" class="ui-button">Terapkan Tanggal</button></div></details>
                    <label class="return-note-filter"><x-ui.icon name="status-filter" :size="14" /><span class="sr-only">Status Transaksi</span><select name="status" onchange="this.form.requestSubmit()"><option value="">Semua Status</option>@foreach(['completed'=>'Selesai','cancelled'=>'Batal'] as $value=>$label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select></label>
                    <label class="return-note-filter"><x-ui.icon name="cashier-filter" :size="14" /><span class="sr-only">Kasir</span><select name="user_id" onchange="this.form.requestSubmit()"><option value="">Semua Kasir</option>@foreach($cashiers as $cashier)<option value="{{ $cashier->id }}" @selected((string)request('user_id') === (string)$cashier->id)>{{ $cashier->name }}</option>@endforeach</select></label>
                    <details class="history-date"><summary>Filter Pembayaran</summary><div class="history-date-panel">
                        <x-ui.field name="payment_status" label="Status Pembayaran"><select id="payment_status" name="payment_status" class="form-input"><option value="">Semua pembayaran</option>@foreach(['paid'=>'Lunas','unpaid'=>'Belum Dibayar','partial'=>'Dibayar Sebagian'] as $value=>$label)<option value="{{ $value }}" @selected(request('payment_status') === $value)>{{ $label }}</option>@endforeach</select></x-ui.field>
                        <x-ui.field name="payment_method" label="Metode Pembayaran"><select id="payment_method" name="payment_method" class="form-input"><option value="">Semua metode</option>@foreach($paymentMethods as $method)<option value="{{ $method }}" @selected(request('payment_method') === $method)>{{ $method === 'cash' ? 'Tunai' : ucfirst($method) }}</option>@endforeach</select></x-ui.field><button class="ui-button" type="submit">Terapkan</button>
                    </div></details>
                    @if(count(request()->except(['view','page','per_page','edit','invoice'])) > 0)<a href="{{ route('transactions.index') }}" class="filter-reset">Reset Filter</a>@endif
                </div>
            </form>
            @if(request('view') === 'cards')
                <div class="catalogue-card-grid">@forelse($transactions as $transaction)<article class="product-card"><header><div><h2><a href="{{ request()->fullUrlWithQuery(['invoice'=>$transaction->id,'edit'=>null]) }}" class="underline">{{ $transaction->invoice_number }}</a></h2><p class="mt-2 text-sm text-muted">{{ $transaction->sold_at?->format('d/m/Y H:i') ?? '–' }}</p></div><x-ui.transaction-status :value="$transaction->status" /></header><dl class="product-card__details"><dt>Produk</dt><dd>{{ $transaction->items->pluck('product_name')->join(', ') ?: '–' }}</dd><dt>Total Item</dt><dd>{{ $transaction->items_sum_quantity ?? 0 }}</dd><dt>Kasir</dt><dd>{{ $transaction->user?->name ?? '–' }}</dd><dt>Total Pembayaran</dt><dd>Rp {{ number_format($transaction->grand_total,2,',','.') }}</dd><dt>Pembayaran</dt><dd><x-ui.transaction-status :value="$transaction->payment_status" payment /></dd></dl><div class="flex flex-wrap gap-3"><a href="{{ request()->fullUrlWithQuery(['invoice'=>$transaction->id,'edit'=>null]) }}" class="ui-button ui-button--secondary">Detail Invoice</a>@if($transaction->status === 'completed')<a href="{{ request()->fullUrlWithQuery(['edit'=>$transaction->id,'invoice'=>null]) }}" class="edit-link">Edit <x-ui.icon name="edit" :size="18" /></a>@endif</div></article>@empty<x-ui.empty-state title="Transaksi tidak ditemukan" description="Belum ada transaksi atau tidak ada yang cocok dengan filter." />@endforelse</div>
            @else
                <div class="catalogue-table-scroll"><table class="catalogue-table"><caption class="sr-only">Daftar histori transaksi penjualan</caption><thead><tr>@foreach(['Invoice/Tanggal','Produk','Total Item','Kasir','Status','Total Pembayaran','Aksi'] as $heading)<th scope="col">{{ $heading }}</th>@endforeach</tr></thead><tbody>
                @forelse($transactions as $transaction)<tr>
                    <td><a class="inline-flex items-center gap-1 font-medium underline" href="{{ request()->fullUrlWithQuery(['invoice'=>$transaction->id,'edit'=>null]) }}"><x-ui.icon name="invoice" :size="14" />{{ $transaction->invoice_number }}</a><span class="mt-1 block text-xs text-muted">{{ $transaction->sold_at?->format('d M Y, H:i') ?? '–' }}</span></td>
                    <td><span>{{ $transaction->items->first()?->product_name ?? '–' }}</span>@if($transaction->items->count() > 1)<details class="mt-1"><summary class="cursor-pointer text-xs text-muted">+{{ $transaction->items->count()-1 }} Lainnya</summary><ul class="mt-2 grid gap-1 text-xs">@foreach($transaction->items->skip(1) as $item)<li>{{ $item->product_name }}</li>@endforeach</ul></details>@endif</td>
                    <td>{{ $transaction->items_sum_quantity ?? 0 }}</td><td><span class="flex items-center gap-2"><x-ui.user-avatar :name="$transaction->user?->name" />{{ $transaction->user?->name ?? '–' }}</span></td><td><x-ui.transaction-status :value="$transaction->status" /></td><td class="whitespace-nowrap">Rp {{ number_format($transaction->grand_total,2,',','.') }}</td>
                    <td>@if($transaction->status === 'completed')<a class="edit-link" href="{{ request()->fullUrlWithQuery(['edit'=>$transaction->id,'invoice'=>null]) }}" aria-label="Edit {{ $transaction->invoice_number }}">Edit <x-ui.icon name="edit" :size="18" /></a>@else<a class="edit-link" href="{{ request()->fullUrlWithQuery(['invoice'=>$transaction->id,'edit'=>null]) }}">Detail</a>@endif</td>
                </tr>@empty<tr><td colspan="7"><x-ui.empty-state title="Transaksi tidak ditemukan" description="Belum ada transaksi atau tidak ada yang cocok dengan filter." /></td></tr>@endforelse</tbody></table></div>
            @endif
            <x-ui.pagination :paginator="$transactions" />
        </section>
        @if($invoice)
            <x-ui.dialog name="detail-invoice" title="Detail Invoice" :show="true" class="invoice-dialog" close-icon="close">
                @include('transactions.partials.invoice',['sale'=>$invoice])
                <footer class="dialog-actions"><button type="button" class="ui-button ui-button--plain" @click="$el.closest('dialog').close()">Tutup Invoice</button>@if($invoice->status === 'completed')<a href="{{ request()->fullUrlWithQuery(['edit'=>$invoice->id,'invoice'=>null]) }}" class="ui-button ui-button--secondary"><x-ui.icon name="edit" :size="18" />Edit Transaksi</a>@endif</footer>
            </x-ui.dialog>
        @endif
        @if($editing)@include('transactions.partials.editor',['sale'=>$editing])@endif
    </div>
</x-app-layout>
