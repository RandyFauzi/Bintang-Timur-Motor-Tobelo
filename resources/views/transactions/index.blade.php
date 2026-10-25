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
                <div class="catalogue-filters history-filters" role="region" aria-label="Filter histori transaksi">
                    <div class="history-date-filter" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.stop="open = false; $refs.trigger.focus()">
                        <button type="button" @class(['filter-trigger', 'is-active' => request()->filled('start_date') || request()->filled('end_date')]) x-ref="trigger" @click="open = !open; if (open) $nextTick(() => $refs.start.focus())" :aria-expanded="open" aria-controls="history-date-panel"><x-ui.icon name="calendar" :size="16" /><span>{{ request()->filled('start_date') || request()->filled('end_date') ? 'Tanggal Dipilih' : 'Semua Tanggal' }}</span><x-ui.icon name="chevron" :size="16" class="rotate-90" /></button>
                        <div id="history-date-panel" class="history-date-panel grid gap-4" x-show="open" x-cloak x-transition.opacity>
                            <p class="text-sm font-medium">Filter Tanggal</p>
                            <div class="grid grid-cols-2 gap-3">
                                <label class="grid min-w-0 gap-2 text-xs font-medium">Tanggal Mulai<input x-ref="start" type="date" name="start_date" value="{{ request('start_date') }}" class="date-filter__input"></label>
                                <label class="grid min-w-0 gap-2 text-xs font-medium">Tanggal Akhir<input type="date" name="end_date" value="{{ request('end_date') }}" class="date-filter__input"></label>
                            </div>
                            <button type="submit" class="ui-button w-full">Terapkan Tanggal</button>
                        </div>
                    </div>
                    <x-ui.multiselect name="status" id="history-status" label="Semua Status" :options="collect(['' => 'Semua Status', 'completed' => 'Selesai', 'cancelled' => 'Batal'])->map(fn (string $label, string $value): object => (object) ['id' => $value, 'name' => $label])->values()" :selected="[request('status', '')]" :multiple="false" :dropdown="true" icon="status-filter" />
                    <x-ui.multiselect name="user_id" id="history-cashier" label="Semua Kasir" :options="$cashiers" :selected="(array) request('user_id', [])" icon="cashier-filter" />
                    <x-ui.multiselect name="payment_status" id="history-payment-status" label="Semua Pembayaran" :options="collect(['' => 'Semua Pembayaran', 'paid' => 'Lunas', 'unpaid' => 'Belum Dibayar', 'partial' => 'Dibayar Sebagian'])->map(fn (string $label, string $value): object => (object) ['id' => $value, 'name' => $label])->values()" :selected="[request('payment_status', '')]" :multiple="false" :dropdown="true" icon="invoice" />
                    <x-ui.multiselect name="payment_method" id="history-payment-method" label="Semua Metode" :options="collect([(object) ['id' => '', 'name' => 'Semua Metode']])->concat($paymentMethods->map(fn (string $method): object => (object) ['id' => $method, 'name' => $method === 'cash' ? 'Tunai' : ucfirst($method)]))" :selected="[request('payment_method', '')]" :multiple="false" :dropdown="true" icon="wallet" />
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
