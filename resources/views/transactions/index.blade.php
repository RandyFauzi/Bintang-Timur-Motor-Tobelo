<x-app-layout>
    <div class="catalogue-page history-page">
        <header class="catalogue-heading"><div><h1>Histori Transaksi</h1><p class="mt-2 text-sm text-muted">Telusuri penjualan, rincian belanja, dan pembayaran pelanggan.</p></div><x-ui.notification-dropdown class="hidden md:inline-flex" /></header>
        <div class="history-summary">
            <x-dashboard.stat-card title="Nilai Penjualan" :value="'Rp '.number_format($summary['revenue'],2,',','.')" note="Sesuai filter yang dipilih · Hanya transaksi selesai" icon="history-banknote" />
            <x-dashboard.stat-card title="Total Transaksi" :value="number_format($summary['transactions']).' Transaksi'" note="Sesuai filter yang dipilih" icon="stock-box" />
            <x-dashboard.stat-card title="Transaksi Batal" :value="number_format($summary['cancelled'])" note="Tidak dihitung dalam penjualan" icon="return-units" />
        </div>
        <section class="catalogue-surface" aria-label="Histori transaksi">
            <form method="GET" action="{{ route('transactions.index') }}">
                <x-ui.catalogue-toolbar export-route="transactions.export" create-label="Tambah Transaksi" :create-href="route('pos.index')" />
                <div class="catalogue-filters history-filters" role="region" aria-label="Filter histori transaksi">
                    <x-ui.inline-date-filter id="history-date-filter" class="history-date-filter" />
                    <x-ui.multiselect name="status" id="history-status" label="Semua Status" :options="collect(['' => 'Semua Status', 'completed' => 'Selesai', 'cancelled' => 'Batal'])->map(fn (string $label, string $value): object => (object) ['id' => $value, 'name' => $label])->values()" :selected="[request('status', '')]" :multiple="false" :dropdown="true" icon="status-filter" />
                    <x-ui.multiselect name="user_id" id="history-cashier" label="Semua Kasir" :options="$cashiers" :selected="(array) request('user_id', [])" icon="cashier-filter" />
                    <x-ui.multiselect name="payment_status" id="history-payment-status" label="Semua Pembayaran" :options="collect(['' => 'Semua Pembayaran', 'paid' => 'Lunas', 'unpaid' => 'Belum Dibayar', 'partial' => 'Dibayar Sebagian'])->map(fn (string $label, string $value): object => (object) ['id' => $value, 'name' => $label])->values()" :selected="[request('payment_status', '')]" :multiple="false" :dropdown="true" icon="invoice" />
                    <x-ui.multiselect name="payment_method" id="history-payment-method" label="Semua Metode" :options="collect([(object) ['id' => '', 'name' => 'Semua Metode']])->concat($paymentMethods->map(fn (string $method): object => (object) ['id' => $method, 'name' => $method === 'cash' ? 'Tunai' : ucfirst($method)]))" :selected="[request('payment_method', '')]" :multiple="false" :dropdown="true" icon="wallet" />
                    @if(count(request()->except(['view','page','per_page','edit','invoice'])) > 0)<a href="{{ route('transactions.index') }}" class="filter-reset">Reset Filter</a>@endif
                </div>
            </form>
            @include('transactions.partials.list')
        </section>
        @if($invoice)
            <x-ui.dialog name="detail-invoice" title="Detail Invoice" :show="true" class="invoice-dialog" close-icon="close">
                @include('transactions.partials.invoice',['sale'=>$invoice])
                <footer class="dialog-actions"><button type="button" class="ui-button ui-button--plain" @click="$el.closest('dialog').close()">Tutup Invoice</button>@if($invoice->status === 'completed')<a href="{{ request()->fullUrlWithQuery(['edit'=>$invoice->id,'invoice'=>null]) }}" class="ui-button ui-button--secondary"><x-ui.icon name="edit" :size="18" />Edit Transaksi</a>@can('cancel-transactions')<button type="button" class="ui-button ui-button--danger" @click="$dispatch('open-cancel-modal', { id: '{{ $invoice->id }}', invoice: '{{ $invoice->invoice_number }}' })">Batalkan Transaksi</button>@endcan @endif</footer>
            </x-ui.dialog>
        @endif
        @if($editing)@include('transactions.partials.editor',['sale'=>$editing])@endif
        @can('cancel-transactions')
            @include('transactions.partials.cancel-modal')
        @endcan
    </div>
</x-app-layout>
