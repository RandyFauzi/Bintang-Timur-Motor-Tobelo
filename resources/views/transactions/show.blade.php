<x-app-layout>
    <div class="catalogue-page">
        <header class="catalogue-heading flex-wrap">
            <div>
                <a href="{{ route('transactions.index') }}" class="text-sm underline">Kembali ke Histori Transaksi</a>
                <h1 class="mt-3">Detail Invoice</h1>
            </div>
            <div class="flex items-center gap-3">
                @if($sale->status === 'completed')
                    <a href="{{ route('transactions.index',['edit'=>$sale->id]) }}" class="ui-button ui-button--secondary"><x-ui.icon name="edit" :size="18" />Edit Transaksi</a>
                    @can('cancel-transactions')
                        <button type="button" class="ui-button ui-button--danger" @click="$dispatch('open-cancel-modal', { id: '{{ $sale->id }}', invoice: '{{ $sale->invoice_number }}' })">Batalkan Transaksi</button>
                    @endcan
                @endif
            </div>
        </header>
        @include('transactions.partials.invoice')
        @can('cancel-transactions')
            @include('transactions.partials.cancel-modal')
        @endcan
    </div>
</x-app-layout>
